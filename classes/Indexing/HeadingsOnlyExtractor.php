<?php namespace ProcessWire;

/**
 * HeadingsOnlyExtractor
 * Extract H1/H2/H3 texts from the RAG view HTML.
 */
trait HeadingsOnlyExtractor
{
    /**
     * Get H1/H2/H3 for a page/lang with a small per-page cache.
     */
    protected function getPageHeadings(Page $page, int $langId = 0, string $html = ""): array
    {
        $empty = ["h1" => "", "h2" => [], "h3" => []];

        if (!$page->id) {
            return $empty;
        }

        $rev = (int) $page->modified;
        $key = "chatai:headings:pid{$page->id}:lid{$langId}:rev{$rev}";
        $cache = $this->wire("cache");

        if ($html !== "") {
            $out = $this->mergeHeadingOutput(
                $this->extractH123FromHtml($html),
                $this->extractTemplateFileHeadings(clone $page),
            );
            $out = $this->normalizeHeadingOutput($out, $page);
            $cache->saveFor("chatai", $key, $out, 3600);
            return $out;
        }

        $hit = $cache->getFor("chatai", $key);
        if ($hit !== null) {
            return $hit;
        }

        $out = $this->normalizeHeadingOutput($empty, $page);
        $cache->saveFor("chatai", $key, $out, 3600);
        return $out;
    }

    /**
     * Render the page template file only, without re-entering Page::render().
     *
     * @param Page $page
     * @return array
     */
    protected function extractTemplateFileHeadings(Page $page): array
    {
        $empty = ["h1" => "", "h2" => [], "h3" => []];
        $filename = $this->templateFilename($page);
        if ($filename === "") {
            return $empty;
        }

        try {
            $html = $this->renderTemplateFileQuietly($filename, $this->templateRenderVars($page, $filename));
        } catch (\Throwable $e) {
            $this->wire("log")->save(
                "chatai",
                "Heading template fallback skipped for {$page->template->name} page {$page->id}: " . $e->getMessage(),
            );
            return $empty;
        }

        return $html !== "" ? $this->extractH123FromHtml($html) : $empty;
    }

    /**
     * Render heading fallback templates without surfacing non-fatal site-template warnings.
     *
     * @param string $filename
     * @param array $vars
     * @return string
     */
    protected function renderTemplateFileQuietly(string $filename, array $vars): string
    {
        set_error_handler(static function ($severity) {
            return (bool) ($severity & (
                E_WARNING |
                E_NOTICE |
                E_USER_WARNING |
                E_USER_NOTICE |
                E_DEPRECATED |
                E_USER_DEPRECATED
            ));
        });

        try {
            return (string) $this->wire("files")->render(
                $filename,
                $vars,
                ["allowedPaths" => [$this->wire("config")->paths->templates]],
            );
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @param Page $page
     * @return string
     */
    protected function templateFilename(Page $page): string
    {
        $config = $this->wire("config");
        $filename = (string) ($page->template->filename ?? "");
        if ($filename !== "" && is_file($filename)) {
            return $filename;
        }

        $candidate = $config->paths->templates . $page->template->name . ".php";
        return is_file($candidate) ? $candidate : "";
    }

    /**
     * Build generic variables for rendering a template file as a fragment.
     *
     * @param Page $page
     * @param string $filename
     * @return array
     */
    protected function templateRenderVars(Page $page, string $filename): array
    {
        $vars = [
            "page" => $page,
            "pages" => $this->wire("pages"),
            "config" => $this->wire("config"),
            "files" => $this->wire("files"),
            "sanitizer" => $this->wire("sanitizer"),
            "user" => $this->wire("user"),
            "input" => $this->wire("input"),
            "modules" => $this->wire("modules"),
            "session" => $this->wire("session"),
            "cache" => $this->wire("cache"),
            "database" => $this->wire("database"),
            "fields" => $this->wire("fields"),
            "templates" => $this->wire("templates"),
            "roles" => $this->wire("roles"),
            "permissions" => $this->wire("permissions"),
            "languages" => $this->wire("languages"),
            "pageTitle" => $this->headingPageTitle($page),
            "homepage" => $this->wire("pages")->get(1),
        ];

        foreach ($this->templateVariableNames($filename) as $name) {
            if (array_key_exists($name, $vars)) {
                continue;
            }

            try {
                $value = $this->wire($name);
            } catch (\Throwable $e) {
                $value = null;
            }

            if ($value !== null) {
                $vars[$name] = $value;
                continue;
            }

            $pageValue = $this->templateVariablePage($name);
            if ($pageValue && $pageValue->id) {
                $vars[$name] = $pageValue;
            }
        }

        return $vars;
    }

    /**
     * @param Page $page
     * @return string
     */
    protected function headingPageTitle(Page $page): string
    {
        foreach (["headline", "title"] as $field) {
            if (!$page->template->hasField($field)) {
                continue;
            }

            $value = method_exists($page, "getUnformatted")
                ? $page->getUnformatted($field)
                : $page->get($field);
            $value = trim((string) $value);
            if ($value !== "") {
                return $value;
            }
        }

        return trim((string) $page->name);
    }

    /**
     * Resolve common site variables conventionally, without hardcoding site-specific modules.
     *
     * @param string $name
     * @return Page|null
     */
    protected function templateVariablePage(string $name): ?Page
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $name)) {
            return null;
        }

        try {
            $page = $this->wire("pages")->get("name=" . $this->wire("sanitizer")->pageName($name) . ", include=all");
        } catch (\Throwable $e) {
            return null;
        }

        return $page && $page->id ? $page : null;
    }

    /**
     * @param string $filename
     * @return array
     */
    protected function templateVariableNames(string $filename): array
    {
        $source = is_file($filename) ? file_get_contents($filename) : "";
        if ($source === "") {
            return [];
        }

        $skip = [
            "GLOBALS" => true,
            "_SERVER" => true,
            "_GET" => true,
            "_POST" => true,
            "_FILES" => true,
            "_COOKIE" => true,
            "_SESSION" => true,
            "_REQUEST" => true,
            "_ENV" => true,
            "this" => true,
        ];
        $names = [];

        foreach (token_get_all($source) as $token) {
            if (!is_array($token) || $token[0] !== T_VARIABLE) {
                continue;
            }

            $name = substr($token[1], 1);
            if ($name !== "" && empty($skip[$name])) {
                $names[$name] = $name;
            }
        }

        return array_values($names);
    }

    /**
     * @param array $primary
     * @param array $fallback
     * @return array
     */
    protected function mergeHeadingOutput(array $primary, array $fallback): array
    {
        $out = array_merge(["h1" => "", "h2" => [], "h3" => []], $primary);
        $fallback = array_merge(["h1" => "", "h2" => [], "h3" => []], $fallback);

        if ($out["h1"] === "" && $fallback["h1"] !== "") {
            $out["h1"] = $fallback["h1"];
        }
        $out["h2"] = array_merge((array) $out["h2"], (array) $fallback["h2"]);
        $out["h3"] = array_merge((array) $out["h3"], (array) $fallback["h3"]);

        return $out;
    }

    /**
     * @param array $out
     * @param Page $page
     * @return array
     */
    protected function normalizeHeadingOutput(array $out, Page $page): array
    {
        $out = array_merge(["h1" => "", "h2" => [], "h3" => []], $out);
        if ($out["h1"] === "") {
            $out["h1"] = $this->headingPageTitle($page);
        }
        $out["h2"] = array_slice(array_values(array_unique($out["h2"])), 0, 12);
        $out["h3"] = array_slice(array_values(array_unique($out["h3"])), 0, 24);

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
