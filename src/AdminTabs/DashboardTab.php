<?php

/*
 * Copyright (c) 2025.
 * Clip Magic - Prue Rowland
 * Web: www.clipmagic.com.au
 * Email: admin@clipmagic.com.au
 *
 * ProcessWire 3.x
 * Copyright (C) 2014 by R
 * Licensed under GNU/GPL
 *
 * https://processwire.com
 */

namespace ChatAI\AdminTabs;

use ProcessWire;
use ProcessWire\Wire;

class DashboardTab
{
    public function build($form, $data): array {

        $m         = \ProcessWire\wire('modules');
        $config    = \ProcessWire\wire('config');
        $languages = \ProcessWire\wire('languages');
        $sanitizer = \ProcessWire\wire('sanitizer');

        $tabName = $languages->_('Dashboard');

        $inputfields = $m->get('InputfieldWrapper');
        $inputfields->addClass('WireTab');
        $inputfields->attr('id', $sanitizer->fieldName($tabName) );

        // TODO add dashboard components
        $content = "<p>Dashboard components here</p>";

        $markUp = $m->get('InputfieldMarkup');
        $markUp->attr('value', $content);

        $inputfields->add($markUp);

        // --- Metrics Preview (read-only) ---
        $preview = $m->get('InputfieldMarkup');
        $preview->label = $m->_('Metrics Preview');
        $preview->collapsed = \ProcessWire\Inputfield::collapsedYes;
        $preview->notes = $m->_('Last 50 lines from /site/assets/logs/chatai_metrics.txt');

        if(!is_dir($config->paths->logs . 'chatai'))
            mkdir($config->paths->logs . 'chatai', $config->chmodDir, true);

        $logPath = $config->paths->logs . 'chatai/chatai_metrics.txt';


        $html = '<div class="chatai-metrics"><table class="uk-table uk-table-small"><thead><tr>'
            . '<th>t</th><th>ok</th><th>err</th><th>ms</th><th>count</th><th>rem</th><th>stop</th><th>model</th></tr></thead><tbody>';

        if (is_file($logPath)) {
            $lines = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach (array_slice(array_reverse($lines), 0, 50) as $line) {
                $j = json_decode($line, true);
                if (!is_array($j)) continue;
                $html .= sprintf(
                    '<tr><td>%s</td><td>%s</td><td>%s</td><td>%d</td><td>%d</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                    htmlspecialchars($j['t'] ?? '', ENT_QUOTES, 'UTF-8'),
                    !empty($j['ok']) ? '✓' : '✗',
                    htmlspecialchars($j['err_code'] ?? '', ENT_QUOTES, 'UTF-8'),
                    (int)($j['latency_ms'] ?? 0),
                    (int)($j['count'] ?? 0),
                    isset($j['remaining']) ? (int)$j['remaining'] : '',
                    !empty($j['stop']) ? '■' : '',
                    htmlspecialchars(($j['model'] ?? '') . ' ' . ($j['endpoint'] ?? ''), ENT_QUOTES, 'UTF-8')
                );
            }
        } else {
            $html .= '<tr><td colspan="8"><em>No metrics yet.</em></td></tr>';
        }
        $html .= '</tbody></table></div>';

        $preview->value = $html;
        $inputfields->add($preview);


        // Observability
        $obs = $m->get('InputfieldFieldset');
        $obs->label = $m->_('Observability');
        $obs->notes = $m->_('Logs compact JSON lines to /site/assets/logs/chatai/chatai_metrics.txt');

        $f1 = $m->get('InputfieldCheckbox');
        $f1->name = 'obs_enabled';
        $f1->label = $m->_('Enable observability logging');
        $f1->value = (int)($data['obs_enabled'] ?? 1);
        $obs->add($f1);

        $f2 = $m->get('InputfieldInteger');
        $f2->name = 'obs_sample_rate';
        $f2->label = $m->_('Sample rate (1 = log every request, 10 = 1 in 10)');
        $f2->value = (int)($data['obs_sample_rate'] ?? 1);
        $f2->min = 1;
        $f2->showIf = 'obs_enabled=1';
        $obs->add($f2);

        $inputfields->add($obs);


        $bf = $m->get('InputfieldFieldset');
        $bf->label = $m->_('Backfill & Reconcile');
        $bf->notes = $m->_('Scan all site pages. Index eligible pages missing from the vector DB; delete vectors for ineligible pages that still have entries.');

        $dry = $m->get('InputfieldCheckbox');
        $dry->name = 'chatai_backfill_dry_run';
        $dry->label = $m->_('Dry run (report only, no changes)');
        $dry->checked = true;
        $bf->add($dry);

        $chunk = $m->get('InputfieldInteger');
        $chunk->name = 'chatai_backfill_chunk';
        $chunk->label = $m->_('Batch size (50–1000)');
        $chunk->value = 200;
        $chunk->min = 50;
        $chunk->max = 1000;
        $bf->add($chunk);

        $btn = $m->get('InputfieldSubmit');
        $btn->name = 'chatai_run_backfill';
        $btn->value = $m->_('Run Backfill & Reconcile');
        $btn->attr('class', 'ui-button ui-priority-primary');
        $bf->add($btn);

        $inputfields->add($bf);



        $chip = $m->get('InputfieldMarkup');
        $bit  = $m->wire('modules')->get('ChatAI')->rag_status_bit ?: (1<<22);
        $shift = (int)round(log($bit,2));
        $chip->label = $m->_('RAG Status Bit');
        $chip->value = "<code>{$bit}</code> (0x" . strtoupper(dechex($bit)) . ", 1&laquo;{$shift})";
        $chip->notes = $m->_('Used to mark pages currently indexed in RAG.');
        $chip->collapsed = \ProcessWire\Inputfield::collapsedYes;
        $inputfields->add($chip);


        $form->add($inputfields);

        $output = [];
        $key = $languages->_('Dashboard');
        $value = $inputfields->render();
        $output[$key] = $value;
        $output['form'] = $form;

        return $output;
    }
}
