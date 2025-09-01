<?php namespace ProcessWire;
/**
 * RAG – ProcessWire-centric, render-first version
 *
 * What this version does:
 * - Treats the page as the source of truth. We render a dedicated view (e.g. /site/templates/chatai-rag.php)
 *   using $files->render('chatai-rag.php', ['page' => $page]) to get clean, chrome-free HTML.
 * - Convert that HTML to plain text (preserving bullets/numbered lists) with WireTextTools->markupToText.
 * - Chunk the text, embed each chunk (OpenAI text-embedding-3-small), and store rows in chatai_vec_chunks.
 * - Retrieval: FULLTEXT prefilter by lang_id, cosine re-rank on embeddings.
 *
 * What it does NOT do:
 * - No field-slot config, no skip-lists. The chatai-rag.php view defines what content is in scope.
 * - No ingestion queue. Keep it simple. Call collectPageText($page, $cfg) when you need to (e.g. on save).
 *
 * Assumptions:
 * - Table name for vectors: chatai_vec_chunks (schema includes INT chunk_index and INT lang_id)
 * - Config keys you already use elsewhere, plus:
 *     - 'context_templates': array or pipe string of template names eligible for indexing (e.g. "basic-page|product")
 *     - 'rag_view': the template partial to render (default 'chatai-rag.php')
 * - Numeric language IDs only (PW Languages module IDs). Single-language sites use lang_id = 0.
 */
class RAG extends Wire {
    /*** TABLE NAMES ***/
    public const CHATAI_VEC_TABLE = 'chatai_vec_chunks';

    public function __construct() { parent::__construct(); }

    /*************************
     * 1) CONFIG HELPERS     *
     *************************/

    /**
     * Return list of template names eligible for indexing (KISS: supports array or pipe string).
     * Examples:
     *   - 'basic-page|product' => ['basic-page','product']
     *   - ['basic-page','product'] stays as-is
     */
    protected function getConfiguredContextTemplates(array $cfg): array {
        $tpls = $cfg['context_templates'] ?? [];
        if (is_string($tpls)) $tpls = explode('|', $tpls);
        $tpls = array_values(array_unique(array_filter(array_map('trim', $tpls))));
        return $tpls;
    }

    /** Page must use a configured template to be indexed. */
    public function shouldIndexPage(Page $page, array $cfg): bool {
        $tpls = $this->getConfiguredContextTemplates($cfg);
        if (!$tpls) return false;
        return in_array($page->template->name, $tpls, true);
    }

    /** Quick probe: does any vector exist for this page? */
    public function hasVectorsForPage(int $pageId): bool {
        $db = $this->wire('database');
        $stmt = $db->prepare("SELECT 1 FROM `" . self::CHATAI_VEC_TABLE . "` WHERE page_id=? LIMIT 1");
        $stmt->execute([$pageId]);
        return (bool) $stmt->fetchColumn();
    }

    /**********************
     * 2) LOW-LEVEL TOOLS *
     **********************/

    /**
     * Chunk text on sentence boundaries when possible, with overlap, preserving paragraphs.
     */
    protected function chunkTextByChars(string $text, int $chunk = 1200, int $overlap = 150): array {
        $t = trim(preg_replace('/[ \t]+/u', ' ', $text));
        $t = preg_replace('/\R{3,}/u', "\n\n", $t); // collapse >2 newlines

        $out = [];
        $len = function_exists('mb_strlen') ? mb_strlen($t, 'UTF-8') : strlen($t);
        if ($len === 0) return $out;

        $i = 0;
        $slop = 200;                                      // lookahead for nicer boundaries
        $minBoundary = (int) max(200, floor($chunk * 0.66));

        while ($i < $len) {
            $maxWindow = min($len - $i, $chunk + $slop);
            $window = function_exists('mb_substr') ? mb_substr($t, $i, $maxWindow, 'UTF-8') : substr($t, $i, $maxWindow);

            $searchLen = function_exists('mb_strlen') ? mb_strlen($window, 'UTF-8') : strlen($window);
            $last = -1;
            foreach (['.', '!', '?'] as $p) {
                $pos = function_exists('mb_strrpos') ? mb_strrpos($window, $p, 0, 'UTF-8') : strrpos($window, $p);
                if ($pos !== false && $pos >= $minBoundary && $pos > $last) $last = $pos;
            }

            if ($last !== -1) {
                $endIdx = $last + 1;
                while ($endIdx < $searchLen) {
                    $ch = function_exists('mb_substr') ? mb_substr($window, $endIdx, 1, 'UTF-8') : substr($window, $endIdx, 1);
                    if ($ch === '"' || $ch === "'" || $ch === ')' || $ch === ']' || $ch === '}') $endIdx++;
                    else break;
                }
                $slice = function_exists('mb_substr') ? mb_substr($window, 0, $endIdx, 'UTF-8') : substr($window, 0, $endIdx);
            } else {
                $slice = function_exists('mb_substr') ? mb_substr($window, 0, min($chunk, $searchLen), 'UTF-8') : substr($window, 0, min($chunk, $searchLen));
            }

            $slice = trim($slice);
            if ($slice === '') break;
            $out[] = $slice;

            $sliceLen = function_exists('mb_strlen') ? mb_strlen($slice, 'UTF-8') : strlen($slice);
            $end = $i + $sliceLen;
            if ($end >= $len) break;

            $next = $end - $overlap;
            if ($next < $len) {
                $lookahead = 40;
                $probe = function_exists('mb_substr') ? mb_substr($t, $next, min($lookahead, $len - $next), 'UTF-8') : substr($t, $next, min($lookahead, $len - $next));
                if ($probe !== '' && preg_match('/^\S/u', $probe)) {
                    if (preg_match('/[ \t\r\n\.\!\?,;:\"\'\)\]\}]/u', $probe, $m, PREG_OFFSET_CAPTURE)) {
                        $next += $m[0][1];
                        while ($next < $len) {
                            $ch = function_exists('mb_substr') ? mb_substr($t, $next, 1, 'UTF-8') : substr($t, $next, 1);
                            if ($ch === ' ' || $ch === "\t" || $ch === "\n" || $ch === "\r") $next++;
                            else break;
                        }
                    }
                }
            }
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

    /** Convert HTML to plain text, preserving lists and spacing */
    protected function toPlainText(string $html): string {
        $wireTextTools = new WireTextTools();
        return trim($wireTextTools->markupToText($html, [
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

    /*********************************
     * 3) RENDER → TEXT → CHUNKS     *
     *********************************/

    /** Delete all vectors for a page (optionally limited to one lang). */
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

    /** Insert per-chunk rows for one language. */
    protected function indexPageTextChunks(Page $page, int $langId, string $text): void {
        $db = $this->wire('database');
        // replace rows for (page, lang)
        $db->prepare("DELETE FROM `" . self::CHATAI_VEC_TABLE . "` WHERE page_id=? AND lang_id=?")
            ->execute([$page->id, $langId]);

        $text = trim($text);
        if ($text === '') return;

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
            $stmtIns->execute([null, null, $page->id, $langId, (int)$idx++, $url, $title, $c, $csv, $now, $now]);
        }
    }

    /**
     * Orchestrate rendering the RAG view and indexing into vectors for each language.
     * - Uses PW to switch $user->language, render, and switch back.
     * - Uses $files->render($view, ['page' => $page]) – if the view is missing, PW will throw.
     */
    public function collectPageText(Page $page, array $cfg): void {
        if (!$this->shouldIndexPage($page, $cfg)) return;

        $files = $this->wire('files');
        $view  = $cfg['rag_view'] ?? 'chatai-rag.php';

        $languages = $this->wire('languages');
        $user = $this->wire('user');

        if ($languages && $languages->count()) {
            $origLang = $user && $user->language ? $user->language : null;
            foreach ($languages as $lang) {
                $user->setLanguage($lang);
                $langId = (int)$lang->id;
                $html = $files->render($view, ['page' => $page]); // PW will error if not found
                $text = $this->toPlainText($html);
                $this->indexPageTextChunks($page, $langId, $text);
            }
            if ($origLang) $user->setLanguage($origLang);
        } else {
            // single-language site – use lang_id = 0
            $html = $files->render($view, ['page' => $page]);
            $text = $this->toPlainText($html);
            $this->indexPageTextChunks($page, 0, $text);
        }
    }

    /****************
     * 4) RETRIEVAL *
     ****************/

    /**
     * FULLTEXT prefilter (per lang), cosine rerank.
     * $prefilter bounds the initial candidate count – tune for your content size.
     */
    public function retrieveTopK(string $query, int $userLangId, int $k = 6, int $prefilter = 60): array {
        $db = $this->wire('database');
        $qVec = $this->embedText($query);

        try {
            $stmt = $db->prepare(
                "SELECT id,page_id,lang_id,chunk_index,title,text,embedding_csv,source_url
                   FROM `" . self::CHATAI_VEC_TABLE . "`
                  WHERE lang_id=? AND MATCH(text) AGAINST (? IN NATURAL LANGUAGE MODE)
                  LIMIT ?"
            );
            $stmt->execute([$userLangId, $query, $prefilter]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $rows = [];
        }

        if (!$rows) {
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
            $r['score'] = $this->cosine($qVec, $vec);
            $scored[] = $r;
        }
        usort($scored, fn($a,$b) => $b['score'] <=> $a['score']);
        return array_slice($scored, 0, $k);
    }

    /** Build a compact context string from chunks (bounded by $maxChars). */
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

    /**
     * Example glue to your chat call – kept as a stub.
     * Construct messages with the built context and call your existing ChatAI chat method.
     */
    public function answerWithRAG(string $userText, ?int $userLangId = null): string {
        if ($userLangId === null) {
            $languages = $this->wire('languages');
            $lang = $this->wire('user')->language ?? ($languages ? $languages->getDefault() : null);
            $userLangId = $lang ? (int)$lang->id : 0;
        }
        $top = $this->retrieveTopK($userText, $userLangId, 6);
        $context = $this->buildContextFromChunks($top);

        // TODO: integrate with your existing chat call. For now, return the context for inspection.
        return $context !== '' ? $context : '[no site context matched – try another query]';
    }
}
// end
