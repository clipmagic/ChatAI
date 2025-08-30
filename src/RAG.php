<?php namespace ProcessWire;
/**
 * RAG — cleaned & slot‑aware
 * - Numeric lang_id everywhere (no alpha codes)
 * - Per-chunk storage with soft sentence boundaries
 * - FULLTEXT prefilter (by lang_id) + cosine re‑rank
 * - Slot/fallback fields (e.g. "title|headline, summary, body")
 * - Safer language fan‑out + integer casting on retrieval
 */

class RAG extends Wire {
    /*** TABLE NAMES ***/
    public const CHATAI_VEC_TABLE    = 'chatai_vec_chunks';
    public const CHATAI_DOCS_TABLE   = 'chatai_docs';
    public const CHATAI_BLOCKS_TABLE = 'chatai_doc_blocks';

    public function __construct() {
        parent::__construct();
    }

    /*************************
     * 1) CONFIG RESOLVERS   *
     *************************/

    /** Whether we already have vectors for a page */
    public function hasVectorsForPage(int $pageId): bool {
        $db = $this->wire('database');
        $stmt = $db->prepare("SELECT 1 FROM `".self::CHATAI_VEC_TABLE."` WHERE page_id=? LIMIT 1");
        $stmt->execute([$pageId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Templates to index. Accepts array or pipe/CSV string; returns ["basic-page","product",...]
     */
    protected function getConfiguredContextTemplates(array $cfg): array {
        $tpls = $cfg['context_templates'] ?? [];
        if (is_string($tpls)) {
            // support either "basic-page|product" or "basic-page, product"
            $tpls = preg_split('/[|,\n\r]+/', $tpls) ?: [];
        }
        $tpls = array_values(array_unique(array_filter(array_map('trim', $tpls))));
        return $tpls;
    }

    /**
     * Fields to index (slot + fallbacks).
     * Input examples:
     *   - "title|headline, summary, body"
     *   - ["title|headline","summary","body"]
     *   - [["title","headline"],["summary"],["body"]]
     * Output: [["title","headline"],["summary"],["body"]]
     */
    protected function getConfiguredContextFields(array $cfg): array {
        $raw = $cfg['context_fields'] ?? [];

        // Normalize into array of strings OR array of arrays
        if (is_string($raw)) {
            $items = preg_split('/[,\n\r]+/', $raw) ?: [];
            $items = array_map('trim', $items);
        } elseif (is_array($raw)) {
            $items = $raw;
        } else {
            $items = [];
        }

        $slots = [];
        foreach ($items as $it) {
            if (is_array($it)) {
                $alts = array_values(array_filter(array_map('trim', $it)));
            } else {
                $alts = array_values(array_filter(array_map('trim', explode('|', (string)$it))));
            }
            if ($alts) $slots[] = $alts;
        }

        // Ensure a title-ish slot exists
        $hasTitleish = false;
        foreach ($slots as $alts) {
            $low = array_map('strtolower', $alts);
            if (in_array('title', $low, true) || in_array('headline', $low, true)) { $hasTitleish = true; break; }
        }
        if (!$hasTitleish) array_unshift($slots, ['title','headline']);

        return $slots;
    }

    /** Page eligibility: template match AND at least one configured field present */
    public function shouldIndexPage(Page $page, array $cfg): bool {
        $tpls = $this->getConfiguredContextTemplates($cfg);
        if ($tpls) {
            $tplName = $page->template instanceof Template ? $page->template->name : (string)$page->template;
            if (!in_array($tplName, $tpls, true)) return false;
        }
        $slots = $this->getConfiguredContextFields($cfg);
        foreach ($slots as $alts) {
            foreach ($alts as $f) {
                if ($page->template->hasField($f)) return true;
            }
        }
        return false;
    }

    /**********************
     * 2) LOW-LEVEL TOOLS *
     **********************/
    protected function chunkTextByChars(string $text, int $chunk = 1200, int $overlap = 150): array {
        // normalize but keep paragraph breaks
        $t = trim(preg_replace('/[ \t]+/u', ' ', $text));
        $t = preg_replace('/\R{3,}/u', "\n\n", $t);

        $out = [];
        $len = function_exists('mb_strlen') ? mb_strlen($t, 'UTF-8') : strlen($t);
        if ($len === 0) return $out;

        $i = 0;
        $slop = 200;                                       // small lookahead for nicer boundaries
        $minBoundary = (int) max(200, floor($chunk * 0.66)); // don't end too early

        while ($i < $len) {
            $maxWindow = min($len - $i, $chunk + $slop);
            $window = function_exists('mb_substr') ? mb_substr($t, $i, $maxWindow, 'UTF-8') : substr($t, $i, $maxWindow);

            // find last sentence boundary (., !, ?) after minBoundary within the window
            $searchLen = function_exists('mb_strlen') ? mb_strlen($window, 'UTF-8') : strlen($window);
            $last = -1;
            foreach (['.', '!', '?'] as $p) {
                $pos = function_exists('mb_strrpos') ? mb_strrpos($window, $p, 0, 'UTF-8') : strrpos($window, $p);
                if ($pos !== false && $pos >= $minBoundary && $pos > $last) $last = $pos;
            }

            $slice = '';
            if ($last !== -1) {
                // include trailing quotes/brackets after punctuation if present
                $endIdx = $last + 1;
                while ($endIdx < $searchLen) {
                    $ch = function_exists('mb_substr') ? mb_substr($window, $endIdx, 1, 'UTF-8') : substr($window, $endIdx, 1);
                    if ($ch === '"' || $ch === "'" || $ch === ')' || $ch === ']' || $ch === '}') $endIdx++;
                    else break;
                }
                $slice = function_exists('mb_substr') ? mb_substr($window, 0, $endIdx, 'UTF-8') : substr($window, 0, $endIdx);
            } else {
                // hard cut
                $slice = function_exists('mb_substr') ? mb_substr($window, 0, min($chunk, $searchLen), 'UTF-8') : substr($window, 0, min($chunk, $searchLen));
            }

            $slice = trim($slice);
            if ($slice === '') break;

            $out[] = $slice;

            // advance from the actual slice end, then apply overlap
            $sliceLen = function_exists('mb_strlen') ? mb_strlen($slice, 'UTF-8') : strlen($slice);
            $end = $i + $sliceLen;
            if ($end >= $len) break;

            $next = $end - $overlap;

            // advance start index to a safe boundary to avoid mid-word starts
            if ($next < $len) {
                $lookahead = 40; // chars to search ahead for a boundary
                $probe = mb_substr($t, $next, min($lookahead, $len - $next), 'UTF-8');

                // if we're not already at whitespace/newline
                if ($probe !== '' && preg_match('/^\S/u', $probe)) {
                    // jump to the next whitespace or punctuation
                    if (preg_match('/[ \t\r\n\.\!\?,;:\"\'\)\]\}]/u', $probe, $m, PREG_OFFSET_CAPTURE)) {
                        $next += $m[0][1];
                        // then skip any following whitespace/newlines
                        while ($next < $len) {
                            $ch = mb_substr($t, $next, 1, 'UTF-8');
                            if ($ch === ' ' || $ch === "\t" || $ch === "\n" || $ch === "\r") $next++;
                            else break;
                        }
                    }
                }
            }
            if ($next <= $i) $next = $end; // guard against non-progress if overlap >= slice
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

    /** OpenAI embeddings via your ChatAI module's API key */
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

    /*********************************
     * 3) PAGE → TEXT → CHUNKS/EMBED *
     *********************************/

    /** get clean text */
    protected function toPlainText($value): string {
        if ($value instanceof \ProcessWire\WireArray) return '';        // repeaters/PageArrays: TODO
        if (is_object($value) && method_exists($value, '__toString')) $value = (string)$value;
        if (!is_string($value)) return '';

        $wireTextTools = new WireTextTools();
        return trim($wireTextTools->markupToText($value, [
            'convertEntities'    => true,
            'splitBlocks'        => "\n\n",
            'listItemPrefix'     => '• ',
            'linksToUrls'        => false,
            'linksToMarkdown'    => false,
            'uppercaseHeadlines' => false,
            'underlineHeadlines' => false,
            'collapseSpaces'     => true,
        ]));
    }

    /** Resolve and concatenate all configured fields in this slot (in order). */
    protected function resolveSlotValue(Page $page, array $alts): string
    {
        $buf = [];
        foreach ($alts as $f) {
            if (!$page->template->hasField($f)) {
                continue;
            }
            $raw = $page->get($f);
            $txt = $this->toPlainText($raw);
            if ($txt !== '') {
                $buf[] = $txt;
            }
        }
        return implode("

", $buf);
    }

/** assemble page text across configured slots */
protected function assembleText(Page $page, array $cfg): string {
    $slots = $this->getConfiguredContextFields($cfg);
    $parts = [];
    foreach ($slots as $alts) {
        $v = $this->resolveSlotValue($page, $alts);
        if ($v !== '') $parts[] = $v;
    }
    return implode("\n\n", $parts);
}

/** delete vectors for a page (optionally a single lang) */
public function deletePageVectors(Page $page, ?int $langId=null): void {
    $db = $this->wire('database');
    if ($langId === null) {
        $stmt = $db->prepare("DELETE FROM `" . self::CHATAI_VEC_TABLE . "` WHERE page_id=?");
        $stmt->execute([$page->id]);
    } else {
        $stmt = $db->prepare("DELETE FROM `" . self::CHATAI_VEC_TABLE . "` WHERE page_id=? AND lang_id=?");
        $stmt->execute([$page->id, $langId]);
    }
}

/** index one language worth of text into per-chunk rows */
protected function indexPageTextChunks(Page $page, int $langId, string $text): void {
    $db = $this->wire('database');
    // Replace rows for this page/lang
    $db->prepare("DELETE FROM `" . self::CHATAI_VEC_TABLE . "` WHERE page_id=? AND lang_id=?")
        ->execute([$page->id, $langId]);

    if ($text === '') return; // nothing to index

    $chunks = $this->chunkTextByChars($text);
    if (!$chunks) return;

    $now = date('Y-m-d H:i:s');
    $stmtIns = $db->prepare(
        "INSERT INTO `" . self::CHATAI_VEC_TABLE . "` 
            (doc_id, block_id, page_id, lang_id, chunk_index, source_url, title, text, embedding_csv, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)"
    );

    $url = $page->httpUrl(true);
    $title = (string)$page->title;
    $idx = 0;
    foreach ($chunks as $c) {
        $vec = $this->embedText($c);
        $csv = $this->packCsv($vec);
        $stmtIns->execute([null, null, $page->id, $langId, $idx++, $url, $title, $c, $csv, $now, $now]);
    }
}

/** orchestrate all languages using PW Languages */
public function collectPageText(Page $page, array $cfg): void {
    if (!$this->shouldIndexPage($page, $cfg)) return;

    $languages = $this->wire('languages');
    $user = $this->wire('user');
    $origLang = $user && $user->language ? $user->language : null;

    if (!$languages) {
        $text = $this->assembleText($page, $cfg);
        $this->indexPageTextChunks($page, 0, $text); // 0 = single-lang site
        return;
    }

    foreach ($languages as $lang) {
        $user->setLanguage($lang);
        $langId = (int)$lang->id;
        $text = $this->assembleText($page, $cfg);
        $this->indexPageTextChunks($page, $langId, $text);
    }

    if ($origLang) $user->setLanguage($origLang);
}

/****************
 * 4) RETRIEVAL *
 ****************/
public function retrieveTopK(string $query, int $userLangId, int $k = 6, int $prefilter = 60): array {
    $db = $this->wire('database');
    $qVec = $this->embedText($query);

    $rows = [];
    try {
        $stmt = $db->prepare(
            "SELECT id,page_id,lang_id,chunk_index,title,text,embedding_csv,source_url
                   FROM `" . self::CHATAI_VEC_TABLE . "`
                  WHERE lang_id=? AND MATCH(text) AGAINST (? IN NATURAL LANGUAGE MODE)
                  LIMIT ?"
        );
        $stmt->execute([$userLangId, $query, $prefilter]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    } catch (\Exception $e) {
        $rows = [];
    }

    if (!$rows) {
        // No FT hit for this lang; widen a bit but bounded
        $stmt2 = $db->prepare(
            "SELECT id,page_id,lang_id,chunk_index,title,text,embedding_csv,source_url
                   FROM `" . self::CHATAI_VEC_TABLE . "`
                  WHERE lang_id=?
                  LIMIT 200"
        );
        $stmt2->execute([$userLangId]);
        $rows = $stmt2->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    $scored = [];
    foreach ($rows as $r) {
        $vec = $this->unpackCsv($r['embedding_csv']);
        $score = $this->cosine($qVec, $vec);
        $scored[] = [
            'id'          => (int)$r['id'],
            'page_id'     => (int)$r['page_id'],
            'lang_id'     => (int)$r['lang_id'],
            'chunk_index' => (int)$r['chunk_index'],
            'title'       => (string)$r['title'],
            'text'        => (string)$r['text'],
            'source_url'  => (string)$r['source_url'],
            'score'       => (float)$score,
        ];
    }
    usort($scored, fn($a,$b) => $b['score'] <=> $a['score']);
    return array_slice($scored, 0, $k);
}

public function buildContextFromChunks(array $chunks, int $maxChars = 2400): string {
    $buf = '';
    foreach ($chunks as $c) {
        $snippet = trim($c['text']);
        $add = "\n\n[Source: {$c['title']} | {$c['source_url']} | lang_id={$c['lang_id']}]\n" . $snippet;
        if (strlen($buf) + strlen($add) > $maxChars) break;
        $buf .= $add;
    }
    return ltrim($buf);
}

public function answerWithRAG(string $userText, ?int $userLangId = null): string {
    if ($userLangId === null) {
        $languages = $this->wire('languages');
        $lang   = $this->wire('user')->language ?? ($languages ? $languages->getDefault() : null);
        $userLangId = $lang ? (int)$lang->id : 0;
    }
    $top = $this->retrieveTopK($userText, $userLangId, 6);
    $context = $this->buildContextFromChunks($top);

    $system = method_exists($this, 'getPrompt') ? $this->getPrompt() : '';
    $system .= "\n\nWhen site context is provided below, answer using it. If context is empty, give a brief helpful answer and offer to focus on site content if preferred.";

    $messages = [
        ['role'=>'system','content'=>$system],
        ['role'=>'user','content'=>$userText . ($context ? "\n\nContext:\n".$context : '')]
    ];
    // return $this->callChat($messages);
    return '[stub] integrate with your chat call';
}

/****************************************
 * 5) OPTIONAL: INGESTION QUEUE + ADAPTER
 ****************************************/
public function enqueuePageDoc(Page $page, ?int $langId = null, array $meta = []): void {
    $db = $this->wire('database');
    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare("INSERT INTO `".self::CHATAI_DOCS_TABLE."` (source, src_ptr, page_id, lang_id, backend, status, attempts, created_at, updated_at, meta_json) VALUES (?,?,?,?, 'docling', 'pending', 0, ?, ?, ?)");
    $stmt->execute(['page', null, $page->id, $langId, $now, $now, json_encode($meta)]);
}

public function claimNextDoc(int $leaseSeconds = 60): ?array {
    $db = $this->wire('database');
    $db->beginTransaction();
    $row = $db->query("SELECT id FROM `".self::CHATAI_DOCS_TABLE."` WHERE (status='pending' OR (status='leased' AND leased_until < NOW())) ORDER BY id ASC LIMIT 1 FOR UPDATE")->fetch(\PDO::FETCH_ASSOC);
    if (!$row) { $db->commit(); return null; }
    $id = (int)$row['id'];
    $until = date('Y-m-d H:i:s', time() + $leaseSeconds);
    $up = $db->prepare("UPDATE `".self::CHATAI_DOCS_TABLE."` SET status='leased', leased_until=?, updated_at=? WHERE id=?");
    $up->execute([$until, date('Y-m-d H:i:s'), $id]);
    $doc = $db->query("SELECT * FROM `".self::CHATAI_DOCS_TABLE."` WHERE id=".$id)->fetch(\PDO::FETCH_ASSOC);
    $db->commit();
    return $doc ?: null;
}

public function processDoc(array $doc, array $cfg): void {
    $db = $this->wire('database');
    $now = date('Y-m-d H:i:s');
    $blocks = [];

    try {
        if ($doc['source'] === 'page') {
            $page = $this->wire('pages')->get((int)$doc['page_id']);
            if (!$page->id) throw new WireException('Page not found');

            // per-language fanout
            $languages = $this->wire('languages');
            $user = $this->wire('user');
            $origLang = $user && $user->language ? $user->language : null;
            if ($languages) {
                foreach ($languages as $lang) {
                    if ($doc['lang_id'] !== null && (int)$doc['lang_id'] !== (int)$lang->id) continue;
                    $user->setLanguage($lang);
                    $langId = (int)$lang->id;
                    $blocks = $this->extractPageBlocks($page, $cfg, $langId);
                    $this->persistBlocksAndVectors((int)$doc['id'], $blocks, $page);
                }
                if ($origLang) $user->setLanguage($origLang);
            } else {
                $blocks = $this->extractPageBlocks($page, $cfg, 0);
                $this->persistBlocksAndVectors((int)$doc['id'], $blocks, $page);
            }
        } else {
            // 'file'|'url' – call external extractor and normalize
            $raw = $this->callExtractor((string)$doc['src_ptr'], (string)($doc['backend'] ?: 'docling'));
            $blocks = $this->normalizeExtracted($raw, (string)($doc['backend'] ?: 'docling'));
            $this->persistBlocksAndVectors((int)$doc['id'], $blocks, null);
        }

        $up = $db->prepare("UPDATE `".self::CHATAI_DOCS_TABLE."` SET status='done', updated_at=? WHERE id=?");
        $up->execute([$now, (int)$doc['id']]);
    } catch (\Throwable $e) {
        $up = $db->prepare("UPDATE `".self::CHATAI_DOCS_TABLE."` SET status='failed', attempts=attempts+1, error_text=?, updated_at=? WHERE id=?");
        $up->execute([substr($e->getMessage(),0,2000), $now, (int)$doc['id']]);
    }
}

/** extract text blocks from a PW page by configured fields */
protected function extractPageBlocks(Page $page, array $cfg, int $langId): array {
    $slots = $this->getConfiguredContextFields($cfg);
    $blocks = [];
    $i = 0;
    foreach ($slots as $alts) {
        $txt = $this->resolveSlotValue($page, $alts);
        if ($txt === '') continue;
        $blocks[] = [
            'block_index' => $i++,
            'lang_id'     => $langId,
            'kind'        => 'field:' . implode('|', $alts),
            'text'        => $this->wire('sanitizer')->textarea($txt),
            'meta'        => ['page_id' => (int)$page->id, 'fields' => $alts]
        ];
    }
    return $blocks;
}

/** persist blocks → chunk/embed → vec rows */
protected function persistBlocksAndVectors(int $docId, array $blocks, ?Page $pageOrNull): void {
    $db = $this->wire('database');
    $now = date('Y-m-d H:i:s');

    $insB = $db->prepare("INSERT INTO `".self::CHATAI_BLOCKS_TABLE."` (doc_id, block_index, lang_id, kind, text, meta_json, created_at) VALUES (?,?,?,?,?,?,?)");
    $insV = $db->prepare(
        "INSERT INTO `".self::CHATAI_VEC_TABLE."` (doc_id, block_id, page_id, lang_id, chunk_index, source_url, title, text, embedding_csv, created_at, updated_at) 
             VALUES (?,?,?,?,?,?,?,?,?,?,?)"
    );

    foreach ($blocks as $b) {
        $insB->execute([$docId, (int)$b['block_index'], (int)($b['lang_id'] ?? 0), (string)$b['kind'], (string)$b['text'], json_encode($b['meta'] ?? []), $now]);
        $blockId = (int)$db->lastInsertId();

        $chunks = $this->chunkTextByChars((string)$b['text']);
        $i = 0;
        foreach ($chunks as $c) {
            $vec = $this->embedText($c);
            $csv = $this->packCsv($vec);
            $insV->execute([
                $docId,
                $blockId,
                $pageOrNull ? $pageOrNull->id : 0,
                (int)($b['lang_id'] ?? 0),
                $i++,
                $pageOrNull ? $pageOrNull->httpUrl(true) : null,
                $pageOrNull ? (string)$pageOrNull->title : ($b['meta']['title'] ?? null),
                $c,
                $csv,
                $now,
                $now
            ]);
        }
    }
}

/** call an external extractor – stub */
protected function callExtractor(string $pathOrUrl, string $backend = 'docling'): array {
    return [];
}

/** normalize extractor-specific payloads into our minimal blocks[] schema */
protected function normalizeExtracted(array $raw, string $backend): array {
    $blocks = [];
    $i = 0;
    if ($backend === 'docling') {
        foreach (($raw['elements'] ?? []) as $el) {
            $txt = trim((string)($el['text'] ?? ''));
            if ($txt === '') continue;
            $blocks[] = [
                'block_index' => $i++,
                'lang_id'     => isset($el['lang']) ? (int)$el['lang'] : 0,
                'kind'        => strtolower((string)($el['type'] ?? 'paragraph')),
                'text'        => $txt,
                'meta'        => $el['meta'] ?? []
            ];
        }
    } else if ($backend === 'unstructured') {
        foreach (($raw['elements'] ?? []) as $el) {
            $txt = trim((string)($el['text'] ?? ''));
            if ($txt === '') continue;
            $blocks[] = [
                'block_index' => $i++,
                'lang_id'     => 0, // map ISO→PW id in your app if needed
                'kind'        => strtolower((string)($el['type'] ?? 'paragraph')),
                'text'        => $txt,
                'meta'        => $el['metadata'] ?? []
            ];
        }
    }
    return $blocks;
}

/** Worker helpers */
public function workerOnce(array $cfg, string $backend = 'docling'): ?int {
    $doc = $this->claimNextDoc();
    if (!$doc) return null;
    $this->processDoc($doc, $cfg);
    return (int)$doc['id'];
}

public function workerLoop(array $cfg, string $backend = 'docling', int $max = 50): void {
    for ($i=0; $i<$max; $i++) {
        $id = $this->workerOnce($cfg, $backend);
        if ($id === null) break;
    }
}
}
// end
