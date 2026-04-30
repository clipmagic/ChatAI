<?php namespace ProcessWire;
/**
 * RAG – ProcessWire-centric, render-first version
 *
 * What this version does:
 * - Treats the page as the source of truth. We render a dedicated RAG view (default /site/templates/chatai-rag.php)
 *   to get clean, chrome-free HTML for indexing.
 * - Convert that HTML to plain text (preserving bullets/numbered lists) with WireTextTools->markupToText.
 * - Chunk the text, embed each chunk, and store rows in chatai_vec_chunks.
 * - Retrieval: FULLTEXT prefilter by lang_id, cosine re-rank on embeddings.
 *
 * What it does NOT do:
 * - No field-slot config, no skip-lists. The configured chatai-rag.php view defines what content is in scope.
 * - No ingestion queue. Keep it simple. Call collectPageText($page, $cfg) when you need to (e.g. on save).
 *
 * Assumptions:
 * - Table name for vectors: chatai_vec_chunks (schema includes INT chunk_index and INT lang_id)
 * - Config keys you already use elsewhere, plus:
 *     - 'context_templates': array or pipe string of template names eligible for indexing (e.g. "basic-page|product")
 *     - 'rag_view': the template partial to render (default 'chatai-rag.php')
 * - Numeric language IDs only (PW Languages module IDs). Single-language sites use lang_id = 0.
 */
class RAG extends Wire
{
    /*** TABLE NAMES ***/
    public const CHATAI_VEC_TABLE = "chatai_vec_chunks";

    public function __construct()
    {
        parent::__construct();
    }

    /*************************
     * 1) CONFIG HELPERS     *
     *************************/

    protected function chatAI(): ?ChatAI
    {
        return $this->wire("modules")->get("ChatAI");
    }

    /** Page must use a configured template to be indexed. */
    public function shouldIndexPage(Page $page, array $cfg): bool
    {
        $chatai = $this->ChatAI();
        if (!$chatai) {
            return false;
        }

        if (!$page->id) {
            return false;
        }

        $config = $this->wire("config");
        $isDev = str_contains($config->httpHost, "ddev.site");

        // Public on live, allowed on ddev
        if (!$page->isPublic() && !$isDev) {
            return false;
        }

        // Still must pass template rules
        if (!$chatai->validTemplate($page, $cfg)) {
            return false;
        }

        if ($page->id === $config->http404PageID) {
            return false;
        }
        if ($page->template && $page->template->name === "http404") {
            return false;
        }
        if ($page->name === "http404") {
            return false;
        }
        // Must be published (hidden is allowed)
        if ($page->isUnpublished()) {
            return false;
        }
        if ($page->isTrash()) {
            return false;
        }

        return true;
    }

    /**
     * Reindex decision:
     * - returns true if page has no vectors OR the latest vector is older than $page->modified
     * - returns false if vectors exist and are up-to-date
     */
    public function getPageVectorStats(int $pageId): array
    {
        $db = $this->wire("database");
        $stmt = $db->prepare(
            "SELECT COUNT(*) AS chunks, MAX(indexed_at) AS indexed_at
         FROM `" .
                self::CHATAI_VEC_TABLE .
                "`
         WHERE page_id=?",
        );
        $stmt->execute([$pageId]);
        $row = (array) $stmt->fetch(\PDO::FETCH_ASSOC);

        return [
            "chunks" => (int) ($row["chunks"] ?? 0),
            "indexed_at" => (string) $row["indexed_at"] ?? "",
        ];
    }

    /**********************
     * 2) LOW-LEVEL TOOLS *
     **********************/

    /**
     * Chunk text on sentence boundaries when possible, with overlap, preserving paragraphs.
     */
    protected function chunkTextByChars(
        string $text,
        int $chunk = 1200,
        int $overlap = 150,
    ): array {
        $t = trim(preg_replace('/[ \t]+/u', " ", $text));
        $t = preg_replace("/\R{3,}/u", "\n\n", $t); // collapse >2 newlines

        $out = [];
        $len = function_exists("mb_strlen")
            ? mb_strlen($t, "UTF-8")
            : strlen($t);
        if ($len === 0) {
            return $out;
        }

        $i = 0;
        $slop = 200; // lookahead for nicer boundaries
        $minBoundary = (int) max(200, floor($chunk * 0.66));

        while ($i < $len) {
            $maxWindow = min($len - $i, $chunk + $slop);
            $window = function_exists("mb_substr")
                ? mb_substr($t, $i, $maxWindow, "UTF-8")
                : substr($t, $i, $maxWindow);

            $searchLen = function_exists("mb_strlen")
                ? mb_strlen($window, "UTF-8")
                : strlen($window);
            $last = -1;
            foreach ([".", "!", "?"] as $p) {
                $pos = function_exists("mb_strrpos")
                    ? mb_strrpos($window, $p, 0, "UTF-8")
                    : strrpos($window, $p);
                if ($pos !== false && $pos >= $minBoundary && $pos > $last) {
                    $last = $pos;
                }
            }

            if ($last !== -1) {
                $endIdx = $last + 1;
                while ($endIdx < $searchLen) {
                    $ch = function_exists("mb_substr")
                        ? mb_substr($window, $endIdx, 1, "UTF-8")
                        : substr($window, $endIdx, 1);
                    if (
                        $ch === '"' ||
                        $ch === "'" ||
                        $ch === ")" ||
                        $ch === "]" ||
                        $ch === "}"
                    ) {
                        $endIdx++;
                    } else {
                        break;
                    }
                }
                $slice = function_exists("mb_substr")
                    ? mb_substr($window, 0, $endIdx, "UTF-8")
                    : substr($window, 0, $endIdx);
            } else {
                $slice = function_exists("mb_substr")
                    ? mb_substr($window, 0, min($chunk, $searchLen), "UTF-8")
                    : substr($window, 0, min($chunk, $searchLen));
            }

            $slice = trim($slice);
            if ($slice === "") {
                break;
            }
            $out[] = $slice;

            $sliceLen = function_exists("mb_strlen")
                ? mb_strlen($slice, "UTF-8")
                : strlen($slice);
            $end = $i + $sliceLen;
            if ($end >= $len) {
                break;
            }

            $next = $end - $overlap;
            if ($next < $len) {
                $lookahead = 40;
                $probe = function_exists("mb_substr")
                    ? mb_substr(
                        $t,
                        $next,
                        min($lookahead, $len - $next),
                        "UTF-8",
                    )
                    : substr($t, $next, min($lookahead, $len - $next));
                if ($probe !== "" && preg_match("/^\S/u", $probe)) {
                    if (
                        preg_match(
                            '/[ \t\r\n\.\!\?,;:\"\'\)\]\}]/u',
                            $probe,
                            $m,
                            PREG_OFFSET_CAPTURE,
                        )
                    ) {
                        $next += $m[0][1];
                        while ($next < $len) {
                            $ch = function_exists("mb_substr")
                                ? mb_substr($t, $next, 1, "UTF-8")
                                : substr($t, $next, 1);
                            if (
                                $ch === " " ||
                                $ch === "\t" ||
                                $ch === "\n" ||
                                $ch === "\r"
                            ) {
                                $next++;
                            } else {
                                break;
                            }
                        }
                    }
                }
            }
            if ($next <= $i) {
                $next = $end;
            } // guard against non-progress
            $i = $next;
        }

        return array_values(array_filter($out, fn($s) => $s !== ""));
    }

    /**
     * @param array $v
     * @return string
     */
    protected function packCsv(array $v): string
    {
        return implode(
            ",",
            array_map(
                fn($x) => rtrim(rtrim(sprintf("%.6f", $x), "0"), "."),
                $v,
            ),
        );
    }

    /**
     * @param string $csv
     * @return array
     */
    protected function unpackCsv(string $csv): array
    {
        return $csv ? array_map("floatval", explode(",", $csv)) : [];
    }

    /**
     * @param array $a
     * @param array $b
     * @return float
     */
    protected function cosine(array $a, array $b): float
    {
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        $n = min(count($a), count($b));
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] * $a[$i];
            $nb += $b[$i] * $b[$i];
        }
        if ($na == 0 || $nb == 0) {
            return 0.0;
        }
        return $dot / (sqrt($na) * sqrt($nb));
    }

    /**
     * @param string $phrase
     * @param array $ignoredTerms
     * @return array
     */
    protected function prepareQueryTerms(string $phrase, array $ignoredTerms = []): array
    {
        $terms = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(trim($phrase)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (!$terms) {
            return [];
        }

        $ignored = [];
        foreach ($ignoredTerms as $term) {
            $term = mb_strtolower(trim((string) $term));
            if ($term !== "") {
                $ignored[$term] = true;
            }
        }

        $out = [];
        foreach (array_unique($terms) as $term) {
            if (mb_strlen($term) < 4) {
                continue;
            }
            if (isset($ignored[$term])) {
                continue;
            }
            $out[] = $term;
        }

        return $out;
    }

    /**
     * @param array $cfg
     * @param int $langId
     * @return array
     */
    protected function collectIgnoredQueryTerms(array $cfg, int $langId): array
    {
        $sets = [];
        foreach (["stop_terms_hard", "stop_terms_soft", "meta_terms"] as $key) {
            $raw = $this->cfgLang($key, $cfg, $langId);
            if ($raw === "") {
                continue;
            }
            $parts = preg_split('~[\r\n,]+~', mb_strtolower($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($parts as $part) {
                $term = trim($part);
                if ($term !== "") {
                    $sets[] = $term;
                }
            }
        }

        return array_values(array_unique($sets));
    }

    /**
     * @param string $phrase
     * @param array $terms
     * @param string $field
     * @param float $exactBoost
     * @param float $termBoost
     * @return float
     */
    protected function scoreFieldMatch(string $phrase, array $terms, string $field, float $exactBoost, float $termBoost): float
    {
        $phrase = mb_strtolower(trim($phrase));
        $field = mb_strtolower(trim($field));

        if ($phrase === "" || $field === "") {
            return 0.0;
        }
        
        $score = 0.0;

        if (mb_stripos($field, $phrase) !== false) {
            $score += $exactBoost;
        }

        foreach ($terms as $term) {
            if (mb_stripos($field, $term) !== false) {
                $score += $termBoost;
            }
        }

        return $score;
    }

    /**
     * @param $rawHeadings
     * @return array|array[]
     */
    protected function decodeHeadingLevels($rawHeadings): array
    {
        if (!is_string($rawHeadings) || trim($rawHeadings) === "") {
            return [];
        }

        $decoded = json_decode($rawHeadings, true);
        if (!is_array($decoded)) {
            return [];
        }

        $levels = [
            "h1" => [],
            "h2" => [],
            "h3" => [],
        ];

        foreach ($decoded as $key => $value) {
            if (is_string($key) && isset($levels[strtolower($key)])) {
                $items = is_array($value) ? $value : [$value];
                foreach ($items as $item) {
                    if (is_scalar($item) && trim((string) $item) !== "") {
                        $levels[strtolower($key)][] = trim((string) $item);
                    }
                }
                continue;
            }

            if (is_array($value)) {
                $tag = strtolower((string) ($value["tag"] ?? $value["level"] ?? ""));
                $text = trim((string) ($value["text"] ?? $value["title"] ?? $value["heading"] ?? ""));
                if ($text !== "" && isset($levels[$tag])) {
                    $levels[$tag][] = $text;
                }
                continue;
            }

            if (is_scalar($value)) {
                $text = trim((string) $value);
                if ($text !== "") {
                    $levels["h3"][] = $text;
                }
            }
        }

        return $levels;
    }

    /**
     * @param string $phrase
     * @param array $terms
     * @param $rawHeadings
     * @param string $title
     * @return float
     */
    protected function scoreHeadingMatches(string $phrase, array $terms, $rawHeadings, string $title = ""): float
    {
        $levels = $this->decodeHeadingLevels($rawHeadings);
        if (!$levels) {
            return 0.0;
        }

        $score = 0.0;
        $titleNorm = mb_strtolower(trim($title));
        foreach ($levels["h1"] as $heading) {
            if ($titleNorm !== "" && mb_strtolower(trim($heading)) === $titleNorm) {
                continue;
            }
            $score += $this->scoreFieldMatch($phrase, $terms, $heading, 0.18, 0.04);
        }
        foreach ($levels["h2"] as $heading) {
            $score += $this->scoreFieldMatch($phrase, $terms, $heading, 0.12, 0.03);
        }
        foreach ($levels["h3"] as $heading) {
            $score += $this->scoreFieldMatch($phrase, $terms, $heading, 0.08, 0.02);
        }

        return $score;
    }

    /** Embeddings via ChatAI's hookable embedding method */
    protected function embedText(string $text): array
    {
        $chatai = $this->wire("modules")->get("ChatAI");
        if (!$chatai) {
            throw new WireException("ChatAI embedding method is not available.");
        }

        try {
            $vector = $chatai->embedText($text);
        } catch (\Throwable $e) {
            throw new WireException($e->getMessage(), 0, $e);
        }

        if (!is_array($vector) || !$vector) {
            throw new WireException("ChatAI embedding method returned an invalid vector.");
        }

        return $vector;
    }

    /*********************************
     * 3) RENDER → TEXT → CHUNKS     *
     *********************************/

    /** Delete all vectors for a page (optionally limited to one lang). */
    public function deletePageVectors(Page $page, ?int $langId = null): void
    {
        $db = $this->wire("database");
        if ($langId === null) {
            $stmt = $db->prepare(
                "DELETE FROM `" . self::CHATAI_VEC_TABLE . "` WHERE page_id=?",
            );
            $stmt->execute([$page->id]);
        } else {
            $stmt = $db->prepare(
                "DELETE FROM `" .
                    self::CHATAI_VEC_TABLE .
                    "` WHERE page_id=? AND lang_id=?",
            );
            $stmt->execute([$page->id, $langId]);
        }
    }

    /** Delete all vectors for a language. */
    public function deleteLanguageVectors(int $langId): void
    {
        $db = $this->wire("database");
        $stmt = $db->prepare(
            "DELETE FROM `" . self::CHATAI_VEC_TABLE . "` WHERE lang_id=?",
        );
        $stmt->execute([$langId]);
    }

    /** Insert per-chunk rows for one language. */
    protected function indexPageTextChunks(
        Page $page,
        int $langId,
        array $content,
    ): void {
        $db = $this->wire("database");
        // replace rows for (page, lang)
        $db->prepare(
            "DELETE FROM `" .
                self::CHATAI_VEC_TABLE .
                "` WHERE page_id=? AND lang_id=?",
        )->execute([$page->id, $langId]);

        $text = trim($content["text"]);
        if ($text === "") {
            return;
        }

        $chunks = $this->chunkTextByChars($text);
        if (!$chunks) {
            return;
        }

        $stmtIns = $db->prepare(
            "INSERT INTO `" .
                self::CHATAI_VEC_TABLE .
                "`
     (id, page_id, lang_id, chunk_index, source_url, title, headings, slug, text, embedding_csv)
     VALUES (?,?,?,?,?,?,?,?,?,?)",
        );

        $url = $page->httpUrl(true);
        $title = (string) $page->title;
        $headings = json_encode($content["heads"]);
        $slug = $page->name;

        $idx = 0;
        foreach ($chunks as $c) {
            $vec = $this->embedText($c);
            $csv = $this->packCsv($vec);

            $stmtIns->execute([
                null,
                $page->id,
                $langId,
                (int) $idx++,
                $url,
                $title,
                $headings,
                $slug,
                $c,
                $csv,
            ]);
        }
    }

    /**
     * Orchestrate rendering the RAG view and indexing into vectors for each language.
     */
    public function collectPageText(Page $page, array $cfg): void
    {
        if (!$this->shouldIndexPage($page, $cfg)) {
            return;
        }
        $user = $this->wire("user");
        $indexer = $this->chatAI()->indexer();
        $pageLangs = $page->getLanguages();

        if ($pageLangs->count > 0) {
            $origLang = $user && $user->language ? $user->language : null;
            foreach ($pageLangs as $lang) {
                $user->setLanguage($lang);
                $langId = (int) $lang->id;
                $content = $indexer->buildForPage($page, $langId);
                $this->indexPageTextChunks($page, $langId, $content);
            }
            if ($origLang) {
                $user->setLanguage($origLang);
            }
        } else {
            $content = $indexer->buildForPage($page, 0);
            $this->indexPageTextChunks($page, 0, $content);
        }
    }

    /****************
     * 4) RETRIEVAL *
     ****************/

    public function retrieveTopKForPage(
        string $query,
        int $ragLangId,
        int $pageId,
        int $k = 6,
        int $prefilter = 60,
        ?User $user = null,
    ): array {
        if ($pageId < 1) {
            return [];
        }

        $pages = $this->wire("pages");
        $page = $pages->get($pageId);

        if (!$page->id || !$page->viewable($user)) {
            return [];
        }

        $db = $this->wire("database");
        $qVec = $this->embedText($query);

        try {
            $stmt = $db->prepare(
                "SELECT id,page_id,lang_id,chunk_index,title,text,embedding_csv,source_url
             FROM `" .
                    self::CHATAI_VEC_TABLE .
                    "`
             WHERE lang_id=? AND page_id=?
               AND MATCH(text) AGAINST (? IN NATURAL LANGUAGE MODE)
             LIMIT ?",
            );
            $stmt->execute([$ragLangId, $pageId, $query, $prefilter]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $rows = [];
        }

        if (!$rows) {
            $stmt2 = $db->prepare(
                "SELECT id,page_id,lang_id,chunk_index,title,text,embedding_csv,source_url
             FROM `" .
                    self::CHATAI_VEC_TABLE .
                    "`
             WHERE lang_id=? AND page_id=?
             LIMIT 200",
            );
            $stmt2->execute([$ragLangId, $pageId]);
            $rows = $stmt2->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        }

        // Score chunks (no page collapse)
        $scored = [];
        foreach ($rows as $r) {
            if (empty($r["source_url"])) {
                continue;
            }
            $vec = $this->unpackCsv($r["embedding_csv"]);
            $r["score"] = $this->cosine($qVec, $vec);
            $scored[] = $r;
        }

        usort($scored, fn($a, $b) => $b["score"] <=> $a["score"]);
        wire('log')->save('chatai', 'HIT retrieveTopKForPage: ' . $query . ' page=' . $pageId);

        return array_slice($scored, 0, $k);
    }

    /**
     * @param string $phrase
     * @param int $userLangId
     * @param int $k
     * @param int $prefilter
     * @param User|null $user
     * @return array
     * @throws WireException
     */
    public function retrieveTopK(
        string $phrase,
        int $userLangId,
        int $k = 6,
        int $prefilter = 60,
        ?User $user = null,
    ): array {
        $user = $user ?: $this->wire("user");

        $db = $this->wire("database");
        $qVec = $this->embedText($phrase);
        $cfg = (array) (($this->chatAI()?->promptService()?->loadPromptSettings()) ?? []);
        $ignoredTerms = $this->collectIgnoredQueryTerms($cfg, $userLangId);
        $queryTerms = $this->prepareQueryTerms($phrase, $ignoredTerms);

        $sql = trim(
            "
        SELECT id,page_id,lang_id,chunk_index,title,headings,text,embedding_csv,source_url
        FROM `" .
                self::CHATAI_VEC_TABLE .
                "`
        WHERE lang_id = ?
          AND MATCH(text) AGAINST (? IN NATURAL LANGUAGE MODE)
        LIMIT ?
    ",
        );

        try {
            $stmt = $db->prepare($sql);
            $stmt->bindValue(1, (int) $userLangId, \PDO::PARAM_INT);
            $stmt->bindValue(2, $phrase, \PDO::PARAM_STR);
            $stmt->bindValue(3, (int) $prefilter, \PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $this->wire("log")->save(
                "chatai",
                "rows query 1 count: " . count($rows),
            );
            /**
             * Fallback for literal phrase queries (e.g. "H2 again")
             * FULLTEXT often misses short technical phrases.
             */
            if (!$rows && strlen($phrase) < 40) {
                $like = "%" . $phrase . "%";

                $sql2 = trim(
                    "
                SELECT id,page_id,lang_id,chunk_index,title,headings,text,embedding_csv,source_url
                FROM `" .
                        self::CHATAI_VEC_TABLE .
                        "`
                WHERE lang_id = ?
                  AND text LIKE ?
                LIMIT ?
            ",
                );

                $stmt2 = $db->prepare($sql2);
                $stmt2->bindValue(1, (int) $userLangId, \PDO::PARAM_INT);
                $stmt2->bindValue(2, $like, \PDO::PARAM_STR);
                $stmt2->bindValue(3, (int) $prefilter, \PDO::PARAM_INT);
                $stmt2->execute();

                $rows = $stmt2->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            }
        } catch (\Throwable $e) {
            $this->wire()->log->save('chatai', $e->getMessage(), "retrieveTopK exception");
            $rows = [];
        }

        // Score all candidate chunks
        $scored = [];
        foreach ($rows as $r) {
            if (empty($r["source_url"])) {
                continue;
            }
            if (empty($r["embedding_csv"])) {
                continue;
            }

            $vec = $this->unpackCsv($r["embedding_csv"]);
            $cosineScore = $this->cosine($qVec, $vec);
            $title = (string) ($r["title"] ?? "");
            $titleBoost = $this->scoreFieldMatch($phrase, $queryTerms, $title, 0.14, 0.03);
            $headingBoost = $this->scoreHeadingMatches($phrase, $queryTerms, $r["headings"] ?? "", $title);
            $r["score"] = $cosineScore + $titleBoost + $headingBoost;
            $scored[] = $r;
        }

        if (!$scored) {
            return [];
        }

        usort($scored, fn($a, $b) => $b["score"] <=> $a["score"]);

        // Permission filter by page->viewable($user)
        $pageIds = [];
        foreach ($scored as $row) {
            $pid = (int) ($row["page_id"] ?? 0);
            if ($pid > 0) {
                $pageIds[$pid] = $pid;
            }
            if (count($pageIds) >= $k * 10) {
                break;
            }
        }

        if ($pageIds) {
            $pages = $this->wire("pages");

            // Bulk-load candidates in one query
            $selector = "id=" . implode("|", $pageIds) . ", include=hidden";
            $cand = $pages->find($selector);

            $allowed = [];
            foreach ($cand as $p) {
                if ($p->id && $p->viewable($user)) {
                    $allowed[(int) $p->id] = true;
                }
            }

            // Filter scored rows to allowed page IDs
            if ($allowed) {
                $scored = array_values(
                    array_filter($scored, function ($row) use ($allowed) {
                        $pid = (int) ($row["page_id"] ?? 0);
                        return $pid > 0 && isset($allowed[$pid]);
                    }),
                );
            } else {
                $scored = [];
            }
        }

        if (!$scored) {
            return [];
        }

        // Collapse to unique pages (keep best chunk per page)
        $byPage = [];
        foreach ($scored as $row) {
            $pid = (int) $row["page_id"];
            if (
                !isset($byPage[$pid]) ||
                $row["score"] > $byPage[$pid]["score"]
            ) {
                $byPage[$pid] = $row;
            }
        }

        // Sort the representative chunks by score and take top K pages
        $uniq = array_values($byPage);
        usort($uniq, fn($a, $b) => $b["score"] <=> $a["score"]);

        return array_slice($uniq, 0, $k);
    }
    
    /** Build a compact context string from chunks (bounded by $maxChars). */
    public function buildContextFromChunks(
        array $chunks,
        int $maxChars = 2400,
    ): string {
        $buf = "";
        foreach ($chunks as $c) {
            $snippet = trim($c["text"]);
            $add =
                "\n\n[Source: {$c["title"]} | {$c["source_url"]} | lang_id={$c["lang_id"]}]\n" .
                $snippet;
            if (strlen($buf) + strlen($add) > $maxChars) {
                break;
            }
            $buf .= $add;
        }
        return ltrim($buf);
    }


    /**
     * @param string $key
     * @param array $cfg
     * @param int $langId
     * @return string
     */
    protected function cfgLang(string $key, array $cfg, int $langId): string
    {
        $k = $key . "__" . $langId;
        return (string) ($cfg[$k] ?? ($cfg[$key] ?? ""));
    }

}
