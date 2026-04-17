<?php namespace ProcessWire;

/**
 * HeadingsOnlyExtractor
 * Render full page (as guest, per language) and extract only H1/H2/H3 texts.
 */
trait HeadingsOnlyExtractor
{
    /**
     * Get H1/H2/H3 for a page/lang with a small per-page cache.
     */
    protected function getPageHeadings(Page $page, int $langId = 0): array
    {
        $config = $this->wire("config");
        $isDev = str_contains($config->httpHost, "ddev.site");

        $empty = ["h1" => "", "h2" => [], "h3" => []];

        if (!$page->id) {
            return $empty;
        }

        $rev = (int) $page->modified;
        $key = "chatai:headings:pid{$page->id}:lid{$langId}:rev{$rev}";
        $cache = $this->wire("cache");
        $hit = $cache->getFor("chatai", $key);
        if ($hit !== null) {
            return $hit;
        }

        $html = $this->renderPageAsGuest($page, $langId);
        if ($html === "") {
            $cache->saveFor("chatai", $key, $empty, 1800);
            return $empty;
        }

        $out = $this->extractH123FromHtml($html);
        $out["h2"] = array_slice(array_values(array_unique($out["h2"])), 0, 12);
        $out["h3"] = array_slice(array_values(array_unique($out["h3"])), 0, 24);
        $cache->saveFor("chatai", $key, $out, 3600);
        return $out;
    }

    /**
     * Render the page exactly as an anonymous user for the given language.
     * Restores the previous user/language after render.
     */
    protected function renderPageAsGuest(Page $page, int $langId): string
    {
        $users = $this->wire("users");
        $languages = $this->wire("languages");

        $prevUser = $this->wire("user");
        $guest = $users->getGuestUser();
        $this->wire("user", $guest);

        $prevLang =
            $languages && $prevUser && $prevUser->language
                ? $prevUser->language
                : null;
        if ($languages && $langId) {
            $lang = $languages->get($langId);
            if ($lang && $lang->id) {
                $languages->setLanguage($lang);
            }
        }

        try {
            $out = (string) $page->render();
        } catch (\Throwable $e) {
            $out = "";
        }

        if ($languages && $prevLang) {
            $languages->setLanguage($prevLang);
        }
        $this->wire("user", $prevUser);
        return $out;
    }

    /**
     * Parse H1/H2/H3 text content from HTML. Comments/scripts/styles are ignored by DOM.
     */
    protected function extractH123FromHtml(string $html): array
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $ok = $dom->loadHTML(
            $html,
            LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET | LIBXML_COMPACT,
        );
        libxml_clear_errors();
        if (!$ok) {
            return ["h1" => "", "h2" => [], "h3" => []];
        }

        $xp = new \DOMXPath($dom);
        $norm = static function ($s) {
            $s = preg_replace("~\s+~u", " ", $s ?? "");
            return trim($s);
        };

        $h1Node = $xp->query("//h1")->item(0);
        $h2List = $xp->query("//h2");
        $h3List = $xp->query("//h3");

        $h2 = [];
        foreach ($h2List as $n) {
            $t = $norm($n->textContent);
            if ($t !== "") {
                $h2[] = $t;
            }
        }
        $h3 = [];
        foreach ($h3List as $n) {
            $t = $norm($n->textContent);
            if ($t !== "") {
                $h3[] = $t;
            }
        }

        return [
            "h1" => $h1Node ? $norm($h1Node->textContent) : "",
            "h2" => $h2,
            "h3" => $h3,
        ];
    }
}
