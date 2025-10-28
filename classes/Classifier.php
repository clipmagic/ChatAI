<?php namespace ProcessWire;

use AllowDynamicProperties;

/**
 * Classifier
 * - Build a lightweight domain dictionary per language and cache it.
 * - Classify user text to decide if RAG should run.
 *
 * Install notes:
 * - Expose module config textareas for:
 *     custom terms (one per line, or "term|2.0"),
 *     stop terms (hard),
 *     stop terms (soft).
 * - Expose an "index version" integer you bump after reindex.
 */

#[\AllowDynamicProperties]
class Classifier extends Wire
{
    protected array $cfgMemo = [];
    public function getCfg(): array {
        if (!empty($this->cfgMemo)) return $this->cfgMemo;
        $chatProcess = $this->wire('modules')->get('ProcessChatAI');
        $cfg = $chatProcess ? (array) $chatProcess->loadPromptSettings() : [];
        $this->cfgMemo = $cfg;
        return $this->cfgMemo;
    }
    /**
     * Build or load the domain dictionary for a language.
     * Returns ['term' => weight, ...]
     */
    public function buildDictionary(): array {
        $cfg   = $this->getCfg();
        // \ProcessWire\WireCache $cache
        $cache = $this->wire('cache');

        $ver = (int)($cfg['index_version'] ?? 1);
        $key = "dict:v{$ver}:all";

        $dict = $cache->getFor('chatai', $key);
        if (is_array($dict) && ($dict['version'] ?? 0) === $ver) return $dict;

        // build from cfg (union across languages via suffixed keys)
        $smalltalk = $this->normMany($this->collectLangStrings($cfg, 'smalltalk_triggers'));
        $meta      = $this->normMany($this->collectLangStrings($cfg, 'meta_terms'));
        $stopHard  = $this->normMany($this->collectLangStrings($cfg, 'stop_terms_hard'));
        $stopSoft  = $this->normMany($this->collectLangStrings($cfg, 'stop_terms_soft'));
        $custom    = $this->normMany($this->collectLangStrings($cfg, 'custom_terms'));
        $question  = $this->normMany($this->collectLangStrings($cfg, 'question_words'));
        $action    = $this->normMany($this->collectLangStrings($cfg, 'action_verbs'));
        $followup  = $this->normMany($this->collectLangStrings($cfg, 'followup_terms'));

        $set = fn(array $arr) => array_fill_keys($arr, true);

        $dict = [
            'version'            => $ver,
            'smalltalk_set'      => $set($smalltalk),
            'meta_terms_set'     => $set($meta),
            'stop_hard_set'      => $set($stopHard),
            'stop_soft_set'      => $set($stopSoft),
            'custom_terms_set'   => $set($custom),
            'question_words_set' => $set($question),
            'action_verbs_set'   => $set($action),
            'followup_terms_set' => $set($followup),
        ];

        $cache->saveFor('chatai', $key, $dict, 3600);
        return $dict;
    }

    /**
     * Classify a message.
     * Returns:
     *  [
     *    'label' => 'info_query|small_talk|meta|action|ambiguous',
     *    'use_rag' => bool,
     *    'confidence' => float,
     *    'matched_terms' => string[],
     *  ]
     */
    public function classify(string $text): array {
        $t = mb_strtolower(trim($text));
        if ($t === '') return ['label' => 'ambiguous', 'use_rag' => false];

        $dict = $this->buildDictionary();

        if ($this->isMeta($t, $dict))      return ['label' => 'meta',       'use_rag' => false];
        if ($this->isGreeting($t, $dict))  return ['label' => 'small_talk', 'use_rag' => false];
        if ($this->isAction($t, $dict))    return ['label' => 'action',     'use_rag' => false];

        $sectionish = $this->hasPageSectionWord($t, $dict);
        $domainHit  = $this->hitsAny($t, $dict['custom_terms_set'] ?? []);
        if ($this->hasQuestionWord($t, $dict) || $domainHit || $sectionish) {
            return ['label' => 'info_query', 'use_rag' => true];
        }

        if ($this->looksLikeFollowup($t, $dict)) {
            return ['label' => 'ambiguous', 'use_rag' => false];
        }

        return ['label' => 'ambiguous', 'use_rag' => false];
    }


    // --- Helpers: config → lists -------------------------------------------------

    // collect base + key__{langId} strings then return array of strings
    protected function collectLangStrings(array $cfg, string $baseKey): array {
        $vals = [];
        if (!empty($cfg[$baseKey]) && is_string($cfg[$baseKey])) $vals[] = $cfg[$baseKey];
        $prefix = $baseKey . '__';
        foreach ($cfg as $k => $v) {
            if (is_string($v) && str_starts_with($k, $prefix) && trim($v) !== '') $vals[] = $v;
        }
        return $vals;
    }
    // "a,b\nc" → ['a','b','c'] lowercased, trimmed, deduped
    protected function normMany(array $vals): array {
        $out = [];
        foreach ($vals as $s) $out = array_merge($out, $this->normList($s));
        return array_values(array_unique($out));
    }
    protected function normList(?string $s): array {
        if (!$s) return [];
        $parts = preg_split('~[\r\n,]+~', $s, -1, PREG_SPLIT_NO_EMPTY);
        $parts = array_map(fn($x) => mb_strtolower(trim($x)), $parts);
        return array_values(array_unique(array_filter($parts, fn($x) => $x !== '')));
    }

    protected function hasQuestionWord(string $t, array $dict): bool {
        if (str_contains($t, '?')) return true;
        foreach ($dict['question_words_set'] as $w => $_) {
            if (mb_stripos($t, $w) !== false) return true;
        }
        return false;
    }

    protected function hasPageSectionWord(string $t, array $dict): bool {
        foreach ($dict['stop_soft_set'] as $w => $_) {
            if (mb_stripos($t, $w) !== false) return true;
        }
        return false;
    }
    protected function isGreeting(string $t, array $dict): bool {
        foreach ($dict['smalltalk_set'] as $w => $_) {
            if (mb_stripos($t, $w) === 0) return true;
        }
        return false;
    }
    protected function isMeta(string $t, array $dict): bool {
        foreach ($dict['meta_terms_set'] as $w => $_) {
            if ($w !== '' && mb_stripos($t, $w) !== false) return true;
        }
        return false;
    }
    protected function isAction(string $t, array $dict): bool {
        foreach ($dict['action_verbs_set'] as $v => $_) {
            if (preg_match('~^(?:please\s+)?' . preg_quote($v, '~') . '\b~ui', $t)) return true;
        }
        return false;
    }


    protected function looksLikeFollowup(string $t, array $dict): bool {
        foreach ($dict['followup_terms_set'] as $w => $_) {
            if (mb_stripos($t, $w) !== false) return true;
        }
        return ($t === '...' || $t === '…');
    }
    protected function hitsAny(string $t, array $set): bool {
        foreach ($set as $term => $_) {
            if ($term !== '' && mb_stripos($t, $term) !== false) return true;
        }
        return false;
    }



    protected function pack(string $label, bool $useRag, float $conf, array $hits): array
    {
        return [
            'label' => $label,
            'use_rag' => $useRag,
            'confidence' => max(0.0, min(1.0, $conf)),
            'matched_terms' => array_values(array_unique($hits)),
        ];
    }

    protected function decide(float $conf, array $hits): array
    {
        if ($conf >= 0.50)  return $this->pack('info_query', true,  $conf, $hits);
        if ($conf >= 0.30)  return $this->pack('ambiguous',  false, $conf, $hits);
        return $this->pack('small_talk', false, $conf, $hits);
    }

    protected function confidence(string $t, float $score, array $hits, array $extras = []): float
    {
        // Base: compress score ~0..3 into 0..1
        $base = max(0.0, min(1.0, $score / 3.0));

        $bump = 0.0;
        if ($this->hasQuestionWord($t))  $bump += 0.20;
        if ($this->hasPageSectionWord($t)) $bump += 0.20;
        if ($this->isGreeting($t))       $bump -= 0.30;
        if ($this->isMeta($t))           $bump -= 0.30;

        foreach ($extras as $k => $v) $bump += (float)$v;

        $conf = $base + $bump;
        return max(0.0, min(1.0, $conf));
    }

    protected function matchScore(string $t, array $dict): array
    {
        // Match dictionary terms by n-grams up to 4 words
        $tokens = $this->tokens($t);
        $n      = count($tokens);
        $hits   = [];
        $sum    = 0.0;

        // Quick single token matches
        for ($i = 0; $i < $n; $i++) {
            $w1 = $tokens[$i];
            if (isset($dict[$w1])) { $hits[] = $w1; $sum += $dict[$w1]; }
        }

        // Multiword phrases (2..4)
        for ($size = 4; $size >= 2; $size--) {
            for ($i = 0; $i <= $n - $size; $i++) {
                $phrase = implode(' ', array_slice($tokens, $i, $size));
                if (isset($dict[$phrase])) {
                    $hits[] = $phrase;
                    $sum += $dict[$phrase] * 1.2; // small bonus for specific phrases
                    $i += ($size - 1); // skip ahead
                }
            }
        }

        // Deduplicate hits
        $hits = array_values(array_unique($hits));

        return [$sum, $hits];
    }


    /* ----------------------- Dictionary building ------------------------ */

    /**
     * Collect seed texts with a source tag.
     * Tries vector table first, then falls back to PW pages.
     * Each row: ['text' => string, 'source' => 'title'|'h1'|'h2'|'h3'|'slug'|'breadcrumb'|'faq_q'|'other']
     */
    protected function collectSeeds(int $langId): array
    {
        $out = [];

        // 1) From vector metadata table if present
        $database = $this->wire('database');
        try {
            $tbl = $database->escapeTable('chatai_vec_chunks');
            // Optional schema: id, lang_id, title, h1, h2, h3, slug, breadcrumb, faq_q
            $q = $database->query("SHOW TABLES LIKE '{$tbl}'");
            if ($q && $q->num_rows > 0) {
                $sql = "SELECT lang_id, title, headings, slug FROM {$tbl} WHERE lang_id=" . (int)$langId . " LIMIT 20000";
                $res = $database->query($sql);
                while ($res && ($row = $res->fetch_assoc())) {
                    foreach (['title','headings','slug'] as $k) {
                        if (!empty($row[$k])) {
                            if($k === 'headings') {
                                $headings = json_decode($row[$k]);
                                $h1 = $headings['h1'];
                                $out[] = ['text' => (string)$h1, 'source' => 'h1'];
                                $h2 = $headings['h2'];
                                $out[] = ['text' => (string)$h2, 'source' => 'h2'];
                                $h3 = $headings['h3'];
                                $out[] = ['text' => (string)$h3, 'source' => 'h3'];
                            } else {
                                $out[] = ['text' => (string)$row[$k], 'source' => $k];
                            }
                        }
                    }
                }
                if (!empty($out)) return $out;
            }
        } catch (\Throwable $e) {
            // Swallow and fall back
        }

        // 2) Fallback: crawl PW pages quickly
        $pages = $this->wire('pages');
        $tt    = $this->wire('modules')->get('WireTextTools');
        $languages = $this->wire('languages');

        $lang = $languages ? $languages->get((int)$langId) : null;
        if ($lang) $languages->setLanguage($lang);

        // Limit scope to viewable, not hidden, not admin
        //$selector = "has_parent!=2, include=visible, template!=admin, limit=10000";
        $selector = "has_parent!=2, template!=admin, limit=10000";
        foreach ($pages->findMany($selector) as $p) {
            $title = $p->get('title');
            if ($title) $out[] = ['text' => (string)$title, 'source' => 'title'];

            // If you store headings in fields, add them here
            foreach (['headline','summary','body'] as $field) {
                if (!$p->hasField($field)) continue;
                $val = (string) $p->get($field);
                if ($val === '') continue;
                $plain = $tt ? $tt->markupToText($val) : strip_tags($val);
                // naive split on line breaks for headings
                foreach (preg_split('~[\r\n]+~', $plain) as $line) {
                    $line = trim($line);
                    if ($line === '') continue;
                    $out[] = ['text' => $line, 'source' => 'other'];
                }
            }

            // Slug and crumbs
            $out[] = ['text' => (string)$p->name, 'source' => 'slug'];
            $out[] = ['text' => implode(' ', array_map(function($item){ return (string)$item->title; }, $p->parents()->slice(1)->explode('title'))), 'source' => 'breadcrumb'];
        }

        return $out;
    }

    protected function extractTerms(string $text): array
    {
        $t = $this->norm($text);
        // keep hyphenated terms, collapse whitespace
        $t = preg_replace('~[^\p{L}\p{N}\s\-]~u', ' ', $t);
        $t = preg_replace('~\s+~u', ' ', $t);
        $t = trim($t);
        if ($t === '') return [];

        $terms = [];
        // keep both phrases and single words up to 4-grams for headings
        $words = explode(' ', $t);
        $n = count($words);
        for ($i = 0; $i < $n; $i++) {
            $w = $words[$i];
            if ($w !== '') $terms[] = $w;
            for ($size = 2; $size <= 4 && $i + $size <= $n; $size++) {
                $terms[] = implode(' ', array_slice($words, $i, $size));
            }
        }
        return $terms;
    }

    protected function tokens(string $t): array
    {
        $t = preg_replace('~[^\p{L}\p{N}\s\-]~u', ' ', $t);
        $t = preg_replace('~\s+~u', ' ', $t);
        $t = trim($t);
        if ($t === '') return [];
        return explode(' ', $t);
    }

    protected function norm(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = preg_replace('~\s+~u', ' ', $s);
        return trim($s);
    }

    /* ------------------------ Config and lookups ------------------------ */

    protected function getIndexVersion(): int
    {
        // Prefer a module setting you bump after reindex.
        $cfg = $this->getCfg();
        if(isset($cfg['index_version'])) return (int) $cfg['index_version'];

        return 1;
    }

    protected function getCustomTerms(int $langId): array
    {
        // Expect textarea with one per line, optional "|weight"
        $m = $this->wire('modules')->get('ProcessChatAI');
        $cfg = $this->getCfg();


        $txt = '';
        if ($m) {
            $key = "custom_terms_$langId";
            $txt = (string) ($cfg[$key] ?? $cfg['custom_terms'] ?? '');
        }
        $out = [];
        foreach (preg_split('~\R~', $txt) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $parts = explode('|', $line, 2);
            $term = $this->norm($parts[0]);
            $w = isset($parts[1]) ? (float)$parts[1] : 1.5;
            $out[] = ['term' => $term, 'weight' => $w];
        }
        return $out;
    }

    protected function getStopTermsHard(int $langId): array
    {
        $cfg = $this->getCfg();
        $txt = '';
        if ($cfg) {
            $key = "stop_terms_hard_$langId";
            $txt = (string) ($cfg['$key'] ?? $mcfg['stop_terms_hard'] ?? '');
        }
        $set = [];
        foreach (preg_split('~\R~', $txt) as $line) {
            $t = $this->norm($line);
            if ($t !== '') $set[$t] = true;
        }
        // Safe defaults
        foreach (['the','a','an','of','for','to','in','on','at','by','with','from','and','or','but','about','info','information','details','hi','hello','hey','please','thanks','thank you','cheers','etc','misc','n/a','tba','tbc'] as $def) {
            $set[$def] = true;
        }
        return $set;
    }

    protected function getStopTermsSoft(int $langId): array
    {
        $cfg = $this->getCfg();
        $txt = '';
        if ($cfg) {
            $key = "stop_terms_soft_$langId";
            $txt = (string) ($cfg['$key'] ?? $cfg['stop_terms_soft'] ?? '');
        }
        $set = [];
        foreach (preg_split('~\R~', $txt) as $line) {
            $t = $this->norm($line);
            if ($t !== '') $set[$t] = true;
        }
        // Safe defaults
        foreach (['services','products','solutions','resources','articles','blog','page','section','today','now','latest','recent','get','have','make','do','provide','offer','use','help','support','can','could','would'] as $def) {
            $set[$def] = true;
        }
        return $set;
    }
}
