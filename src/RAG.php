<?php
/*
 * Copyright (c) 2025.
 * Clip Magic - Prue Rowland
 * Web: www.clipmagic.com.au
 * Email: admin@clipmagic.com.au
 *
 * ProcessWire 3.x
 * Copyright (C) 2014 by R
 * Licensed under GNU/GPL
 *
 * https://processwire.com
 */

namespace ProcessWire;

class RAG extends Wire
{
    const CHATAI_VEC_TABLE = 'chatai_vec_chunks';

    protected $process;

    public function construct()
    {
        parent::__construct();


    }

    // Vector testing

    public function hasVectorsForPage(int $pageId): bool {
        $db = $this->wire('database');
        $stmt = $db->prepare("SELECT 1 FROM `".self::CHATAI_VEC_TABLE."` WHERE page_id=? LIMIT 1");
        $stmt->execute([$pageId]);
        return (bool) $stmt->fetchColumn();
    }


    /**
     * Templates to index, read from chatai_prompt.json via _loadPromptSettings()
     * Accepts array or comma/newline-separated string; returns ["basic-page","product",...]
     */
    protected function getConfiguredContextTemplates(array $cfg): array
    {
        $tpls = $cfg['context_templates'] ?? [];
        if (is_string($tpls))
            $tpls = explode('|', $tpls);
        $tpls = array_values(array_unique(array_filter(array_map('trim', $tpls))));
        return $tpls;
    }

    /**
     * Fields to index, read from chatai_prompt.json via _loadPromptSettings()
     * - Supports "pipes" for fallbacks per slot, e.g. "title|headline, summary, body"
     * - Auto-prepends "title|headline" if missing
     * Returns an array of slots, e.g. [["title","headline"],["summary"],["body"]]
     */
    public function getConfiguredContextFields(array $cfg): array
    {
        $raw = $cfg['context_fields'] ?? [];
        $items = explode('|', $raw);
        if(!in_array('title', $items))
            $items[] = 'title';

        if(!in_array('headline', $items))
            $items[] = 'headline';
        return $items;
    }

    public function collectPageText(Page $page, array $cfg): void {
        $tpl = $page->template;
        $vectorTpls = $this->getConfiguredContextTemplates($cfg);
        if(!in_array($tpl->name, $vectorTpls)) return;
        $vectorFlds = $this->getConfiguredContextFields($cfg);

        $languages = $this->wire('languages');
        if(!$languages) {
            $parts = [];
            foreach ($vectorFlds as $f) {
                if(!$tpl->hasField($f)) continue;
                $parts[] = $page->$f;
            }
            $this->indexPageContent($page, $parts, $cfg);
        } else {
            $user = $this->wire('user');
            $userLang = $user->language;
            foreach ($languages as $lang) {
                $parts = [];
                $user->setLanguage($lang);
                foreach ($vectorFlds as $f) {
                    if(!$tpl->hasField($f)) continue;
                    $parts[] = $page->$f;
                    $lang = $user->language->id;
                }
                $this->indexPageContent($page, $parts, $cfg, $lang);
                $user->language = $userLang;
            }
        }
    }

    /** 3) Chunking and math */
    protected function chunkTextByChars(string $text, int $chunk = 1200, int $overlap = 150): array {
        // Normalize whitespace but preserve paragraph breaks
        $text = trim(preg_replace('/[ \t]+/u', ' ', $text));
        $text = preg_replace('/\R{3,}/u', "\n\n", $text);

        $out = [];
        $i = 0;
        $len = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);

        $slop = 200;                                // small lookahead to find a nice boundary
        $minBoundary = (int) max(200, floor($chunk * 0.66)); // don't end too early

        while ($i < $len) {
            $maxWindow = min(($len - $i), $chunk + $slop);
            $window = function_exists('mb_substr') ? mb_substr($text, $i, $maxWindow, 'UTF-8') : substr($text, $i, $maxWindow);

            // Try to end on the last sentence boundary within the window
            $slice = '';
            if ($maxWindow > 0) {
                $searchLen = function_exists('mb_strlen') ? mb_strlen($window, 'UTF-8') : strlen($window);
                $last = -1;
                foreach (['.', '!', '?'] as $p) {
                    $pos = function_exists('mb_strrpos') ? mb_strrpos($window, $p, 0, 'UTF-8') : strrpos($window, $p);
                    if ($pos !== false && $pos >= $minBoundary && $pos > $last) $last = $pos;
                }
                if ($last !== -1) {
                    // Include trailing quotes/brackets right after punctuation
                    $endIdx = $last + 1;
                    while ($endIdx < $searchLen) {
                        $ch = function_exists('mb_substr') ? mb_substr($window, $endIdx, 1, 'UTF-8') : substr($window, $endIdx, 1);
                        if ($ch === '"' || $ch === "'" || $ch === ')' || $ch === ']' || $ch === '}') $endIdx++;
                        else break;
                    }
                    $slice = function_exists('mb_substr') ? mb_substr($window, 0, $endIdx, 'UTF-8') : substr($window, 0, $endIdx);
                }
            }
            if ($slice === '') {
                // Hard cut at chunk size if no good boundary
                $slice = function_exists('mb_substr') ? mb_substr($window, 0, min($chunk, $maxWindow), 'UTF-8') : substr($window, 0, min($chunk, $maxWindow));
            }

            $slice = trim($slice);
            if ($slice === '') break;

            $out[] = $slice;

            // Advance based on the ACTUAL slice end, not the nominal chunk target
            $sliceLen = function_exists('mb_strlen') ? mb_strlen($slice, 'UTF-8') : strlen($slice);
            $end = $i + $sliceLen;
            if ($end >= $len) break;

            $next = $end - $overlap;
            if ($next <= $i) $next = $end; // guard against non-progress
            $i = $next;
        }

        return array_values(array_filter($out, fn($s) => $s !== ''));
    }

    protected function packCsv(array $v): string {
        return implode(',', array_map(fn($x) => rtrim(rtrim(sprintf('%.6f', $x), '0'), '.'), $v));
    }
    protected function unpackCsv(string $csv): array { return $csv ? array_map('floatval', explode(',', $csv)) : []; }

    protected function cosine(array $a, array $b): float {
        $dot = 0.0; $na = 0.0; $nb = 0.0; $n = min(count($a), count($b));
        for ($i=0; $i<$n; $i++) { $dot += $a[$i]*$b[$i]; $na += $a[$i]*$a[$i]; $nb += $b[$i]*$b[$i]; }
        if ($na == 0 || $nb == 0) return 0.0;
        return $dot / (sqrt($na)*sqrt($nb));
    }

    /** 4) Embeddings (multilingual) */
    protected function embedText(string $text): array {
        $chatai = $this->wire('modules')->get('ChatAI');
        $http = new WireHttp();
        $http->setHeader('Authorization', 'Bearer ' . $chatai->api_key);
        $http->setHeader('Content-Type', 'application/json');
        $payload = [ 'model' => 'text-embedding-3-small', 'input' => $text ];
        $res = $http->post('https://api.openai.com/v1/embeddings', json_encode($payload));
        if (!$res) throw new WireException('Embedding API error');
        $json = json_decode($res, true);
        if (!isset($json['data'][0]['embedding'])) throw new WireException('Invalid embedding response');
        return $json['data'][0]['embedding'];
    }

    /** 5) Should we index this page? */
    public function shouldIndexPage(Page $page, array $cfg): bool {
        $tpls = $this->getConfiguredContextTemplates($cfg);
        if (!$tpls) return false;
        return in_array($page->template->name, $tpls, true);
    }

    /** 6) Index one page in one language */
    public function indexPageContent(Page $page, array $parts, array $cfg, string $lang = 'en'): void {
        if (!$this->shouldIndexPage($page, $cfg)) return;
        $db = $this->wire('database');
        $text = $this->wire('sanitizer')->textarea(implode("\n\n", $parts));
        if ($text === '') return;

        $lang_id = $this->wire('user')->language->id;

        $stmtDel = $db->prepare("DELETE FROM `".self::CHATAI_VEC_TABLE."` WHERE page_id=? AND lang_id=?");
        $stmtDel->execute([$page->id, $lang_id]);

        $chunks = $this->chunkTextByChars($text);
        $now = date('Y-m-d H:i:s');

        $stmtIns = $db->prepare("INSERT INTO `".self::CHATAI_VEC_TABLE."` (page_id, lang_id, chunk_index, source_url, title, text, embedding_csv, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)");
        $idx = 0;
        $url = $page->httpUrl(true);

        foreach ($chunks as $c) {
            $vec = $this->embedText($c);
            $csv = $this->packCsv($vec);

            $stmtIns->execute([
                $page->id,
                $lang_id,
                $idx++,
                $url,
                $page->title,
                $c,
                $csv,
                $now,
                $now
            ]);
        }
    }

    /** 8) Retrieval: FULLTEXT prefilter then cosine */
    public function retrieveTopK(string $query, string $userLang = 'default', int $k = 6, int $prefilter = 60): array {
        $db = $this->wire('database');
        $qVec = $this->embedText($query);

        $rows = [];
        try {
            $stmt = $db->prepare("SELECT id,page_id,lang,chunk_index,title,text,embedding_csv,source_url
                              FROM `".self::CHATAI_VEC_TABLE."`
                              WHERE lang=? AND MATCH(text) AGAINST (? IN NATURAL LANGUAGE MODE)
                              LIMIT ?");
            $stmt->execute([$userLang, $query, $prefilter]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            $rows = [];
        }

        if (!$rows) {
            $stmt2 = $db->prepare("SELECT id,page_id,lang,chunk_index,title,text,embedding_csv,source_url
                               FROM `".self::CHATAI_VEC_TABLE."` LIMIT 400");
            $stmt2->execute();
            $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $scored = [];
        foreach ($rows as $r) {
            $vec = $this->unpackCsv($r['embedding_csv']);
            $r['score'] = $this->cosine($qVec, $vec);
            $scored[] = $r;
        }
        usort($scored, fn($a,$b) => $b['score'] <=> $a['score']);
        return array_slice($scored, 0, $k);
    }

    /** 9) Build a context string for chat */
    public function buildContextFromChunks(array $chunks, int $maxChars = 2400): string {
        $buf = '';
        foreach ($chunks as $c) {
            $snippet = trim($c['text']);
            $add = "\n\n[Source: {$c['title']} | {$c['source_url']} | lang={$c['lang']}]\n".$snippet;
            if (strlen($buf) + strlen($add) > $maxChars) break;
            $buf .= $add;
        }
        return ltrim($buf);
    }

    /** 10) Example glue: use inside your chat path */
    public function answerWithRAG(string $userText, string $userLang = 'en'): string {
        $top = $this->retrieveTopK($userText, $userLang, 6);
        $context = $this->buildContextFromChunks($top);

        $system = $this->getPrompt();
        $system .= "\n\nWhen site context is provided below, answer using it. If context is empty, give a brief helpful answer and offer to focus on site content if preferred.";

        $messages = [
            ['role'=>'system','content'=>$system],
            ['role'=>'user','content'=>$userText . ($context ? "\n\nContext:\n".$context : '')]
        ];

        // return $this->callChat($messages); // integrate with your chat call
        return '[stub] integrate with your chat call';
    }

    /** 12) Optional normalization for queries */
    public function normalizeQuery(string $q): string {
        $q = trim(mb_strtolower(preg_replace('/\s+/', ' ', $q)));
        return $q;
    }

    public function deletePageVectors(Page $page) {
        $db = $this->wire('database');
        $stmt = $db->prepare("DELETE FROM `" . self::CHATAI_VEC_TABLE . "` WHERE page_id=?");
        $stmt->execute([$page->id]);

    }
// END OF VECTOR TESTING

}