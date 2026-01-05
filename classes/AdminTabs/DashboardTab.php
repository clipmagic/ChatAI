<?php
namespace ChatAI\AdminTabs;

use ProcessWire;
use ProcessWire\Wire;

class DashboardTab
{
    public function build($form, $data): array {

        $m         = \ProcessWire\wire('modules');
        $languages = \ProcessWire\wire('languages');

        $inputfields = $m->get('InputfieldWrapper');
        $inputfields->addClass('WireTab');
        $inputfields->attr('name', 'dashboard');
        $inputfields->attr('id', $m->_('Dashboard'));

        // --- General: chunk on save ---
        $f = $m->get('InputfieldCheckbox');
        $f->attr('name+id', 'chunk_on_save');
        $f->label = $m->_('Update ChatAI on Save');
        $f->value = (int)($data['chunk_on_save'] ?? 1);
        $f->autocheck = 1;
        $f->notes = $m->_("When 'On', each page will be updated on save.\nRecommend setting to 'Off' when bulk updating pages, then using the Backfill & Reconcile later.");
        $inputfields->add($f);

        // --- Analytics / KPI thresholds ---
        $kpi = $m->get('InputfieldFieldset');
        $kpi->label = $m->_('Analytics & KPIs');
        $kpi->notes = $m->_('These thresholds control when the Insights panel raises warnings.');
        $kpi->collapsed = true;

        // Success rate warning threshold (%)
        $f = $m->get('InputfieldInteger');
        $f->name = 'obs_kpi_success_warn_pct';
        $f->label = $m->_('Success rate warning threshold (%)');
        $f->description = $m->_('Below this success rate the dashboard will flag issues. Default is 85%.');
        $f->value = (int)($data['obs_kpi_success_warn_pct'] ?? 85);
        $f->min = 1;
        $f->max = 100;
        $f->columnWidth(33);
        $kpi->add($f);

        // Average latency warning (ms)
        $f = $m->get('InputfieldInteger');
        $f->name = 'obs_kpi_latency_warn_ms';
        $f->label = $m->_('Average latency warning (ms)');
        $f->description = $m->_('Above this average latency the dashboard will warn that the bot may feel slow. Default is 5000 ms.');
        $f->value = (int)($data['obs_kpi_latency_warn_ms'] ?? 5000);
        $f->min = 100;
        $f->max = 60000;
        $f->columnWidth(33);
        $kpi->add($f);

        // Average latency high (ms)
        $f = $m->get('InputfieldInteger');
        $f->name = 'obs_kpi_latency_high_ms';
        $f->label = $m->_('Average latency high (ms)');
        $f->description = $m->_('Above this average latency the dashboard will highlight performance as poor. Default is 8000 ms.');
        $f->value = (int)($data['obs_kpi_latency_high_ms'] ?? 8000);
        $f->min = 100;
        $f->max = 60000;
        $f->columnWidth(34);
        $kpi->add($f);

        $inputfields->add($kpi);

        // --- Dashboard layout / charts ---
        if(file_exists(__DIR__ .'/dashboard-layout.php')) {
            $files  = \ProcessWire\wire('files');
            $input  = \ProcessWire\wire('input');
            $view   = __DIR__ . '/dashboard-layout.php';

            $days = (int) $input->get('days') ?: 7;
            $days = max(1, min($days, 60));

            $eventSummary = [];
            $volumeData   = [];
            $snapshot     = [];

            $chatAI      = $m->get('ChatAI');
            if($chatAI && method_exists($chatAI, 'logger')) {
                $logger = $chatAI->logger();

                if($logger && method_exists($logger, 'fetchSummary')) {
                    $eventSummary = $logger->fetchSummary();
                }
                if ($logger && method_exists($logger, 'fetchDailyVolume')) {
                    $days       = 7;
                    $volumeData = $logger->fetchDailyVolume($days);
                }
                if (method_exists($logger, 'buildInsightsSnapshot')) {
                    $snapshot = $logger->buildInsightsSnapshot($days);
                }
            }

            $content = $files->render(
                $view,
                [
                    'eventSummary' => $eventSummary,
                    'volumeData'   => $volumeData,
                    'snapshot'     => $snapshot,
                    'days'         => $days,
                    'page'         => $chatAI ? $chatAI->wire('page') : \ProcessWire\wire('page'),
                ],
                ['allowedPaths' => [__DIR__ . '/']]
            );
        } else {
            $content = "<p>Dashboard components here</p>";
        }

        $markUp = $m->get('InputfieldMarkup');
        $markUp->val($content);
        $inputfields->add($markUp);

        // --- Insights (read-only) ---
        $chatprocess = $m->get('ProcessChatAI');
        $insights = $m->get('InputfieldMarkup');
        $insights->label = $m->_('Insights');
        $insights->attr('id', 'chatAI-dashboard-insights');
        $json = json_decode($chatprocess->renderInsightsJson());
        $insights->value = $json->html ?? '';
        $inputfields->add($insights);

        // --- RAG: Reindex & Reconcile ---
        $bf = $m->get('InputfieldFieldset');
        $bf->label = $m->_('Update Vector DB (RAG)');
        $bf->description = $m->_(
            'Scan site pages using the selector below. ' .
            'Eligible pages missing from the vector database will be indexed; ' .
            'vectors for pages that no longer match the selector will be removed.'
        );

        // Selector
//        $selector = $m->get('InputfieldText');
//        $selector->name = 'rag_selector';
//        $selector->label = $m->_('Page selector');
//        $selector->value = 'has_parent!=2, include=all, template!=admin, id!=$http404, template!=http404';
//        $selector->notes = $m->_(
//            'ProcessWire selector used to find pages for indexing. ' .
//            'Default: has_parent!=2, include=all, template!=admin, id!=$http404, template!=http404 ' .
//            'Do not include id>, sort, or limit; these are applied automatically.'
//        );
//        $bf->add($selector);

        // Start ID
        $startId = $m->get('InputfieldInteger');
        $startId->name = 'rag_start_id';
        $startId->label = $m->_('Start from page ID');
        $startId->value = 0;
        $startId->min = 0;
        $startId->notes = $m->_(
            'Only process pages with an ID greater than this value. ' .
            'Use to resume a previous run.'
        );
        $startId->columnWidth(50);
        $bf->add($startId);

        // Batch size
        $batch = $m->get('InputfieldInteger');
        $batch->name = 'rag_batch_size';
        $batch->label = $m->_('Batch size (50–1000)');
        $batch->value = 200;
        $batch->min = 50;
        $batch->max = 1000;
        $batch->columnWidth(50);
        $bf->add($batch);

        // Dry run
        $dry = $m->get('InputfieldCheckbox');
        $dry->name = 'rag_dry_run';
        $dry->label = $m->_('Dry run (report only, no changes)');
        $dry->checked = true;
        $bf->add($dry);

        // Submit
        $btn = $m->get('InputfieldSubmit');
        $btn->name = 'rag_run_reindex';
        $btn->value = $m->_('Run Re-index & Reconcile');
        $btn->attr('class', 'ui-button ui-priority-primary');
        $bf->add($btn);

        $inputfields->add($bf);


        $form->add($inputfields);

        $output = [];
        $key = $languages->_('Dashboard');
        $value = $inputfields->render();
        $output[$key] = $value;
        $output['form'] = $form;

        return $output;
    }
}
