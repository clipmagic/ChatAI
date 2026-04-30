<?php namespace ProcessWire;

/**
 * ChatAIIndexer
 * - Per-page scoped render
 * - Uses chatai-rag.php as the source of truth for indexed content
 * - Writes headings + text to the vector pipeline
 */
require_once __DIR__ . '/HeadingsOnlyExtractor.php';

class ChatAIIndexer extends Wire
{
    use HeadingsOnlyExtractor;

    /**
     * Resolve the configured RAG view path with safe fallbacks.
     *
     * @return array{path:string, allowedPaths:array<int, string>}
     */
    protected function resolveRagView(): array
    {
        $files = $this->wire('files');
        $config = $this->wire('config');
        $chatai = $this->wire('modules')->get('ChatAI');

        $configured = trim((string) ($chatai->rag_view ?? ''));
        if($configured === '') {
            $configured = '/site/modules/ChatAI/classes/RAG/chatai-rag.php';
        }

        $root = rtrim((string) $config->paths->root, '/');
        if($root !== '' && $root[0] !== '/') {
            $root = '/' . $root;
        }
        $candidatePath = $root . '/' . ltrim($configured, '/');
        $candidate = realpath($candidatePath) ?: $candidatePath;
        if($files->exists($candidate)) {
            return [
                'path' => $candidate,
                'allowedPaths' => [dirname($candidate) . '/'],
            ];
        }

        $defaultTemplate = $config->paths->templates . 'chatai-rag.php';
        if($files->exists($defaultTemplate)) {
            return [
                'path' => $defaultTemplate,
                'allowedPaths' => [$config->paths->templates],
            ];
        }

        $moduleViewDir = $config->paths('ChatAI') . 'classes/RAG/';
        return [
            'path' => $moduleViewDir . 'chatai-rag.php',
            'allowedPaths' => [$moduleViewDir],
        ];
    }

    /** Build or rebuild a single page for one language */
    public function buildForPage(Page $page, int $langId): array
    {
        $files = $this->wire('files');
        $ragView = $this->resolveRagView();
        $html = $files->render($ragView['path'], ['page' => $page], ['allowedPaths', $ragView['allowedPaths']]);

        $tt = new WireTextTools();
        $text = $tt->markupToText($html);

        $title = trim((string) $page->get('headline|title'));
        $template = $page->template->name;
        $path = $page->path;

        $context = implode("\n", array_filter([
                "Template: {$template}",
                $title !== '' ? "Page: {$title}" : '',
                "Path: {$page->path}",
            ])) . "\n\n";

        $content = [];
        $content['text'] = $context . $text;
        $content['heads'] = $this->getPageHeadings($page, $langId);
        $content['slug'] = $page->name;

        return $content;
    }



    /* ------------------------------ helpers ------------------------------ */

    /** Return current index_version from prompt settings (defaults to 1) */
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
