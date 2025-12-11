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
        $chatProcess = $this->wire('modules')->get('ProcessChatAI');
        if (!$chatProcess || !method_exists($chatProcess, 'loadPromptSettings')) return 1;

        $cfg = (array) $chatProcess->loadPromptSettings();
        return (int) ($cfg['index_version'] ?? 1);
    }

    /** Increase index_version inside ProcessChatAI prompt settings (busts caches) */
    public function bumpIndexVersion(): int
    {
        // @var \ProcessWire\ProcessChatAI $proc
        $chatProcess = $this->wire('modules')->get('ProcessChatAI');
        if (!$chatProcess || !method_exists($chatProcess, 'loadPromptSettings') || !method_exists($chatProcess, 'savePromptSettingsJson')) {
            $this->wire('log')->save('chatai', 'bumpIndexVersion: ProcessChatAI methods unavailable');
            return 1;
        }

        $cfg  = (array) $chatProcess->loadPromptSettings();
        $next = (int) ($cfg['index_version'] ?? 1) + 1;
        $cfg['index_version'] = $next;

        $json = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $this->wire('log')->save('chatai', 'bumpIndexVersion: json_encode failed');
            return $next;
        }

        return $next;
    }
}


