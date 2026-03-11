<?php namespace ProcessWire;

/**
 * IndexContentExtractor
 * Render a page as guest, prune chrome, pick main content, extract H1–H3.
 * No template changes required.
 */

/*TinyHtmlMinifier is still required in IndexContentExtractor for reliable
  DOM/heading extraction.
  Without it, text chunks may still be generated, and headings from repeater-rendered
  content can be missed.
*/

/* TODO
   Investigate why heading extraction from repeater-rendered content depends
   on TinyHtmlMinifier; determine whether the root issue is malformed rendered HTML,
   DOM parsing fragility, or heading extraction logic.
*/
require_once (__DIR__ . '/tiny-html-minifier/src/TinyHtmlMinifier.php');

class IndexContentExtractor extends Wire
{
    /**
     * Extract main content + headings for indexing
     * @return array ['html' => string, 'headings' => ['h1' => string, 'h2' => string[], 'h3' => string[]]]
     */
    public function extract(Page $page, int $langId = 0): array
    {
        if(!$page->id || !$page->viewable()) {
            return ['html' => '', 'headings' => ['h1' => '', 'h2' => [], 'h3' => []]];
        }


        $html = $this->renderAsGuest($page, $langId);
        if($html === '') return ['html' => '', 'headings' => ['h1' => '', 'h2' => [], 'h3' => []]];

        $html = $this->stripHeadAndScripts($html);

        $options = [];
        $minifier = new \Minifier\TinyHtmlMinifier($options);
        $html = $minifier->minify($html);


        $dom = $this->domFromHtml($html);
        if(!$dom) {
            return ['html' => $html, 'headings' => $this->extractHeadingsFromHtml($html)];
        }

        $xp = new \DOMXPath($dom);

        // Remove site chrome
        $this->removeBySelectors($dom, $xp, $this->getExcludeSelectors());

        // Find main content
        $candidates = $this->queryBySelectors($xp, $this->getCandidateSelectors());
        $node = $this->pickBestNode($candidates, $xp);
        if(!$node) {
            $fallback = $xp->query('//main|//article|//section|//div');
            $node = $this->pickBestNode($fallback, $xp);
        }

        if(!$node) {
            $body = $dom->getElementsByTagName('body')->item(0) ?: $dom->documentElement;
            $clean = $this->innerHtml($body);
            return ['html' => $clean, 'headings' => $this->extractHeadingsFromHtml($clean)];
        }

        $scoped = $this->innerHtml($node);
        $heads  = $this->extractHeadingsFromNode($node);

        return ['html' => $scoped, 'headings' => $heads];
    }

    /* ===================== rendering and sanitising ===================== */

    protected function renderAsGuest(Page $page, int $langId): string
    {
        $users = $this->wire('users');
        $languages = $this->wire('languages');

        $prevUser = $this->wire('user');
        $guest = $users->getGuestUser();
        $this->wire('user', $guest);

        $prevLang = $languages ? $this->wire('user')->language : null;
        if($languages && $langId) {
            $lang = $languages->get((int)$langId);
            if($lang && $lang->id) $languages->setLanguage($lang);
        }

        try { $out = (string) $page->render(); } catch(\Throwable $e) { $out = ''; }

        if($languages && $prevLang) $languages->setLanguage($prevLang);
        $this->wire('user', $prevUser);

        return $out;
    }

    protected function stripHeadAndScripts(string $html): string
    {
        $html = preg_replace('~<head\b[^>]*>.*?</head>~is', '', $html);
        $html = preg_replace('~<(script|style|noscript)\b[^>]*>.*?</\1>~is', '', $html);
        return $html ?? '';
    }

    protected function domFromHtml(string $html): ?\DOMDocument
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $ok = $dom->loadHTML($html, LIBXML_NOWARNING|LIBXML_NOERROR|LIBXML_NONET|LIBXML_COMPACT);
        libxml_clear_errors();
        return $ok ? $dom : null;
    }

    /* ========================= selector utilities ======================= */

    protected function getCandidateSelectors(): array
    {
        $cfg = $this->wire('cache')->getFor('chatai', 'promptSettings');
        if(!$cfg) {
            $m = $this->wire('modules')->get('ChatAI');
            $svc = $m->promptService();
            $cfg = $svc->loadPromptSettings();
        }

        $raw = (string)($cfg['rag_candidate_selectors'] ?? "article, [role='main'], .content, .page-content, .entry-content, .post-content, #content");
        return $this->splitCsv($raw);
    }

    protected function getExcludeSelectors(): array
    {
        $cfg = $this->wire('cache')->getFor('chatai', 'promptSettings');
        if(!$cfg) {
            $m = $this->wire('modules')->get('ChatAI');
            $svc = $m->promptService();
            $cfg = $svc->loadPromptSettings();
        }

        $raw = (string)($cfg['rag_exclude_selectors'] ?? "header, footer, nav, aside, form[role='search'], [role='banner'], [role='navigation'], [role='contentinfo'], .sidebar, .breadcrumbs, .menu, .mega-menu, .toolbar, .cookie, .consent, .newsletter, .promo, .ad, .related, .share, .social, #header, #footer");
        return $this->splitCsv($raw);
    }

    protected function splitCsv(string $csv): array
    {
        $out = [];
        foreach(explode(',', $csv) as $s) {
            $s = trim($s);
            if($s !== '') $out[] = $s;
        }
        return $out;
    }

    protected function queryBySelectors(\DOMXPath $xp, array $selectors): \DOMNodeList
    {
        $parts = [];
        foreach($selectors as $sel) $parts[] = $this->cssToXpath($sel);
        $expr = implode(' | ', array_filter($parts));
        if($expr === '') {
            $expr = '//article | //*[@role="main"] | //*[(contains(@class,"content") or contains(@class,"page-content") or contains(@class,"entry-content") or contains(@class,"post-content"))] | //*[@id="content"]';
        }
        return $xp->query($expr);
    }

    protected function removeBySelectors(\DOMDocument $dom, \DOMXPath $xp, array $selectors): void
    {
        if(!$selectors) return;
        $exprs = [];
        foreach($selectors as $sel) $exprs[] = $this->cssToXpath($sel);
        $expr = implode(' | ', array_filter($exprs));
        if($expr === '') return;
        foreach($xp->query($expr) as $n) {
            if($n->parentNode) $n->parentNode->removeChild($n);
        }
    }

    /**
     * Minimal CSS to XPath mapper for tag, #id, .class, [attr="value"]
     */
    protected function cssToXpath(string $sel): string
    {
        $sel = trim($sel);
        if($sel === '') return '';

        if(preg_match('~^\[(\w+)\s*=\s*[\'"]([^\'"]+)[\'"]\]$~', $sel, $m)) {
            return '//*[@' . $m[1] . '="' . $m[2] . '"]';
        }
        if($sel[0] === '#')  return '//*[@id="' . substr($sel,1) . '"]';
        if($sel[0] === '.') {
            $c = substr($sel,1);
            return '//*[contains(concat(" ", normalize-space(@class), " "), " ' . $c . ' ")]';
        }
        if(preg_match('~^(\w+)(\[(\w+)\s*=\s*[\'"]([^\'"]+)[\'"]\])?$~', $sel, $m)) {
            return empty($m[2]) ? ('//' . $m[1]) : ('//' . $m[1] . '[@' . $m[3] . '="' . $m[4] . '"]');
        }
        return '';
    }

    /* ============================= scoring ============================== */

    protected function pickBestNode(\DOMNodeList $nodes, \DOMXPath $xp): ?\DOMNode
    {
        if(!$nodes || !$nodes->length) return null;

        $best = null;
        $bestScore = 0.0;

        foreach($nodes as $n) {
            $text = trim($this->textContent($n));
            if($text === '') continue;

            $len = mb_strlen($text);
            $hCount = $xp->query('.//h1 | .//h2 | .//h3', $n)->length;
            $blkCount = max(1, $xp->query('.//section | .//div | .//p', $n)->length);

            $density = $hCount / $blkCount;
            $score = ($len / 500.0) + (3.0 * $density);

            if($score > $bestScore) {
                $bestScore = $score;
                $best = $n;
            }
        }
        return $best;
    }

    /* ============================ extraction ============================ */

    protected function extractHeadingsFromNode(\DOMNode $node): array
    {
        $xp = new \DOMXPath($node->ownerDocument);
        $h1 = $xp->query('.//h1', $node)->item(0);
        $h2 = $xp->query('.//h2', $node);
        $h3 = $xp->query('.//h3', $node);

        return [
            'h1' => $h1 ? trim($this->textContent($h1)) : '',
            'h2' => $this->collectTexts($h2),
            'h3' => $this->collectTexts($h3),
        ];
    }

    protected function extractHeadingsFromHtml(string $html): array
    {
        $dom = $this->domFromHtml($html);
        if(!$dom) return ['h1' => '', 'h2' => [], 'h3' => []];
        $body = $dom->getElementsByTagName('body')->item(0) ?: $dom->documentElement;
        if(is_null($body)) return [];
        return $this->extractHeadingsFromNode($body);
    }

    protected function collectTexts(\DOMNodeList $nodes): array
    {
        $out = [];
        foreach($nodes as $n) {
            $t = trim($this->textContent($n));
            if($t !== '') $out[] = $t;
        }
        return $out;
    }

    protected function textContent(\DOMNode $n): string
    {
        $txt = $n->textContent ?? '';
        $txt = preg_replace('~\s+~u', ' ', $txt);
        return trim($txt);
    }

    protected function innerHtml(\DOMNode $n): string
    {
        $out = '';
        foreach(iterator_to_array($n->childNodes) as $child) {
            $out .= $n->ownerDocument->saveHTML($child);
        }
        return $out;
    }
}
