<?php namespace ProcessWire;

/**
 * ChatAIIndexer — calls IndexContentExtractor at index time only.
 * - Per-page scoped render
 * - Per-page cache
 * - Writes headings + text to your vector pipeline (stubbed below)
 */
require_once __DIR__ . '/HeadingsOnlyExtractor.php';

class ChatAIIndexer extends Wire
{
    use HeadingsOnlyExtractor;

    /** Build or rebuild a single page for one language */
    public function buildForPage(Page $page, int $langId): array
    {
        $files = $this->wire('files');
        $config = $this->wire('config');

        if($files->exists($config->paths->templates . 'chatai-rag.php')) {
            $html = $files->render('chatai-rag.php', ['page' => $page]);
        } else {
            $ragViewPath = $config->paths('ChatAI') . 'classes/RAG/';
            $view =  $ragViewPath . 'chatai-rag.php';
            $html = $files->render($view, ['page' => $page], ['allowedPaths', [$ragViewPath]]);
        }

        $tt = new WireTextTools();
        $content = [];
        $content['text'] = $tt->markupToText($html);
        $content['heads'] = $this->getPageHeadings($page, $langId);
        $content['slug']  = $page->name;

        return $content;
    }


    /* ------------------------------ helpers ------------------------------ */


    /** Return current index_version from ProcessChatAI prompt settings (defaults to 1) */
    public function getIndexVersion(): int
    {
        // @var \ProcessWire\ProcessChatAI $proc
        $chatai = $this->wire('modules')->get('ChatAI');
        if (!$chatai || !method_exists($chatai, 'promptService')) return 1;

        $cfg = (array) $chatai->promptService()->loadPromptSettings();
        return (int) ($cfg['index_version'] ?? 1);
    }

    /** Increase index_version inside ProcessChatAI prompt settings (busts caches) */
    public function bumpIndexVersion(): int
    {
        $chatai = $this->wire('modules')->get('ChatAI');
        $svc = $chatai ? $chatai->promptService() : null;
        if(!$svc) return 1;

        $cfg = (array) $svc->loadPromptSettings();
        $old = (int) ($cfg['index_version'] ?? 1);
        $next = $old + 1;
        $cfg['index_version'] = $next;

        if(!$svc->savePromptSettings($cfg)) {
            $this->wire('log')->save('chatai', 'bumpIndexVersion: savePromptSettings failed');
            return $old;
        }

        $this->wire('cache')->deleteFor('chatai', "dict:v{$old}:all");
        return $next;
    }
}


