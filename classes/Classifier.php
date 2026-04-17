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
        $chatai = $this->wire('modules')->get('ChatAI');
        $svc = $chatai ? $chatai->promptService() : null;
        $cfg = $svc ? (array) $svc->loadPromptSettings() : [];
        $this->cfgMemo = $cfg;
        return $this->cfgMemo;
    }


    public static function dictionaryPrefixes(): array
    {
        // These are the *prefixes* used by buildDictionary() via collectLangStrings($cfg, $prefix)
        return [
            'smalltalk_triggers',
            'meta_terms',
            'stop_terms_hard',
            'stop_terms_soft',
            'custom_terms',
            'question_words',
            'action_verbs',
            'followup_terms',
        ];
    }



    /**
     * Build or load the domain dictionary for a language.
     * Returns ['term' => weight, ...]
     */
    public function buildDictionary(): array {
        $cfg   = $this->getCfg();
        $cache = $this->wire('cache');

        $ver = (int)($cfg['index_version'] ?? 1);
        $key = "dict:v{$ver}:all";

        $dict = $cache->getFor('chatai', $key);
        if (is_array($dict) && ($dict['version'] ?? 0) === $ver) return $dict;

        // build from cfg (union across languages via suffixed keys)
        $smalltalk   = $this->normMany($this->collectLangStrings($cfg, 'smalltalk_triggers'));
        $meta        = $this->normMany($this->collectLangStrings($cfg, 'meta_terms'));
        $stopHard    = $this->normMany($this->collectLangStrings($cfg, 'stop_terms_hard'));
        $stopSoft    = $this->normMany($this->collectLangStrings($cfg, 'stop_terms_soft'));
        $custom      = $this->normMany($this->collectLangStrings($cfg, 'custom_terms'));
        $question    = $this->normMany($this->collectLangStrings($cfg, 'question_words'));
        $followup    = $this->normMany($this->collectLangStrings($cfg, 'followup_terms'));
        $infoActs    = $this->normMany($this->collectLangStrings($cfg, 'info_action_verbs'));
        $blockedActs = $this->normMany($this->collectLangStrings($cfg, 'blocked_action_verbs'));


        $set = fn(array $arr) => array_fill_keys($arr, true);

        $dict = [
            'version'            => $ver,
            'smalltalk_set'      => $set($smalltalk),
            'meta_terms_set'     => $set($meta),
            'stop_hard_set'      => $set($stopHard),
            'stop_soft_set'      => $set($stopSoft),
            'custom_terms_set'   => $set($custom),
            'question_words_set' => $set($question),
            'followup_terms_set' => $set($followup),
            'info_action_verbs_set'   => $set($infoActs),
            'blocked_action_verbs_set'=> $set($blockedActs),
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
        // Blocked actions first (prevents "change/delete/update" getting into RAG)
        if ($this->isBlockedAction($t, $dict)) return ['label' => 'blocked_action', 'use_rag' => false];

        // Info-actions should use RAG (summarise/list/show/translate/etc.)
        if ($this->isInfoAction($t, $dict))    return ['label' => 'info_query', 'use_rag' => true];

        $sectionish = $this->hasPageSectionWord($t, $dict);
        $domainHit  = $this->hitsAny($t, $dict['custom_terms_set'] ?? []);
        if ($this->hasQuestionWord($t, $dict) || $domainHit || $sectionish) {
            return ['label' => 'info_query', 'use_rag' => true];
        }

        // Treat as follow-up only when it's short and referential.
        // Prevents full requests like "tell me about flowers" from being misrouted.
        if ($this->looksLikeFollowup($t, $dict) && $this->wordCount($t) <= 2) {
            return ['label' => 'ambiguous', 'use_rag' => false];
        }

        return ['label' => 'ambiguous', 'use_rag' => false];
    }


    // --- Helpers: config → lists -------------------------------------------------

    protected function wordCount(string $t): int {
        $t = trim($t);
        if ($t === '') return 0;
        return count(preg_split('~\s+~u', $t, -1, PREG_SPLIT_NO_EMPTY));
    }



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

    protected function isBlockedAction(string $t, array $dict): bool {
        return $this->hitsAny($t, $dict['blocked_action_verbs_set'] ?? []);
    }

    protected function isInfoAction(string $t, array $dict): bool {
        return $this->hitsAny($t, $dict['info_action_verbs_set'] ?? []);
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

    protected function noAccess(string $t, array $set): bool {

        return false;
    }

}
