<?php namespace ProcessWire;

$rangeDays    = isset($snapshot['range_days']) ? (int) $snapshot['range_days'] : (int) ($days ?? 7);
$totalChats    = (int) ($snapshot['total_chats']    ?? 0);
$totalMessages = (int) ($snapshot['total_messages'] ?? 0);
$totalErrors   = (int) ($snapshot['total_failed']   ?? 0);
$totalCutoffs  = (int) ($snapshot['total_cutoffs']  ?? 0);
$totalBlocked  = (int) ($snapshot['total_blocked']  ?? 0);
$rateLimited   = (int) ($snapshot['rate_limited']   ?? 0);


$eventSummary = isset($eventSummary) && is_array($eventSummary) ? $eventSummary : [];
$eventSummaryJson = json_encode($eventSummary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$volumeData = isset($volumeData) && is_array($volumeData) ? $volumeData : [];
$volumeLabels = [];
$volumeTotals = [];

foreach ($volumeData as $row) {
    $volumeLabels[] = (string) ($row['date'] ?? '');
    $volumeTotals[] = (int) ($row['total'] ?? 0);
}

$volumeLabelsJson = json_encode($volumeLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$volumeTotalsJson = json_encode($volumeTotals, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$dashboardConfig = [
    'requestsLabel'   => __('Requests'),
    'todayLabel'      => __('Today'),
    'last7Label'      => __('Last 7 days'),
    'last30Label'     => __('Last 30 days'),
    'replyLabel'      => __('Replies'),
    'cutoffLabel'    => __('Cutoffs'),
    'blockedLabel'    => __('Blocked'),
    'rateLimitedLabel'    => __('Rate limited'),
    'noDataLabel'    => __('No data'),

    'kpiUrl'          => $page->url . '?api=kpiSnapshot',
    'volumeUrl'       => $page->url . '?api=volumeData',
    'insightsUrl'     => $page->url . '?api=insights',
    'obslogExportUrl' => $page->url . '?api=obslogExport',
    'obslogPruneUrl'  => $page->url . '?api=obslogPrune',
];

?>
<script>
    window.chatAIEventSummary  = <?php echo $eventSummaryJson ?: '{}'; ?>;
    window.chatAIVolumeLabels  = <?php echo $volumeLabelsJson ?: '[]'; ?>;
    window.chatAIVolumeTotals  = <?php echo $volumeTotalsJson ?: '[]'; ?>;
    window.chatAIDashboard     = <?php echo json_encode($dashboardConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<div class="chatai-dashboard">

    <header class="chatai-dashboard-header">
        <div class="chatai-dashboard-title">
            <h2><?= __('Chat activity'); ?></h2>
            <p class="chatai-dashboard-sub">
                <?= __('Requests, errors and performance for the selected period.'); ?>
            </p>
        </div>

        <div class="chatai-dashboard-controls">
            <div class="chatai-range-picker" data-current="7">
                <span class="chatai-range-label"><?= __('Date range'); ?>:</span>

                <a href="#"
                   class="chatai-range-link is-active"
                   data-range="7">
                    <?= $dashboardConfig['last7Label']; ?>
                </a>

                <a href="#"
                   class="chatai-range-link"
                   data-range="1">
                    <?= $dashboardConfig['todayLabel']; ?>
                </a>

                <a href="#"
                   class="chatai-range-link"
                   data-range="30">
                    <?= $dashboardConfig['last30Label']; ?>
                </a>
            </div>
        </div>
    </header>


    <section class="chatai-dashboard-kpis" aria-label="ChatAI summary metrics">
        <div class="chatai-dashboard-kpi-grid">
            <article class="chatai-dashboard-kpi">
                <h3><?= __('Chats'); ?></h3>
                <p class="chatai-dashboard-kpi-value">
                    <span id="kpi-chats"><?= $totalChats; ?></span>
                </p>
                <p class="chatai-dashboard-kpi-note">
                    <span id="kpi-chats-note">
                        <?= sprintf(__('in the last %d days'), $rangeDays); ?>
                    </span>
                </p>
            </article>

            <article class="chatai-dashboard-kpi">
                <h3><?= __('Messages'); ?></h3>
                <p class="chatai-dashboard-kpi-value">
                    <span id="kpi-messages"><?= $totalMessages; ?></span>
                </p>
            </article>

            <article class="chatai-dashboard-kpi">
                <h3><?= __('Errors / Cutoffs'); ?></h3>
                <p class="chatai-dashboard-kpi-value">
                    <span id="kpi-errors-cutoffs"><?= $totalErrors + $totalCutoffs; ?></span>
                </p>
                <p class="chatai-dashboard-kpi-note">
                    <span id="kpi-errors-cutoffs-note">
                        <?= sprintf(__('Errors: %d, cutoffs: %d'), $totalErrors, $totalCutoffs); ?>
                    </span>
                </p>
            </article>

            <article class="chatai-dashboard-kpi">
                <h3><?= __('Blocked / Rate limited'); ?></h3>
                <p class="chatai-dashboard-kpi-value">
                    <span id="kpi-blocked"><?= $totalBlocked; ?></span>
                </p>
                <p class="chatai-dashboard-kpi-note">
                    <span id="kpi-blocked-note">
                        <?= sprintf(__('Blocked: %d, rate limited: %d'), $totalBlocked, $rateLimited); ?>
                    </span>
                </p>
            </article>
        </div>
    </section>



    <section class="chatai-dashboard-row" aria-label="ChatAI charts">
        <div class="chatai-dashboard-chart">
            <div class="chatai-dashboard-chart-header">
                <h3><?=__('Message volume')?></h3>
            </div>
            <canvas id="chatai-chart-volume"></canvas>
        </div>

        <div class="chatai-dashboard-chart chatai-dashboard-chart-events">
            <div class="chatai-dashboard-chart-header">
                <h3><?=__('Event types')?></h3>
                <p class="chatai-dashboard-chart-subtitle"><?=__('Normal vs issues')?></p>
            </div>
            <canvas id="chatai-chart-events"></canvas>
        </div>

    </section>
    <section class="chatai-actions">

        <details>
            <summary class="InputfieldHeader uk-form-label chatai-summary">
                <span><i class="fa fa-fw fa-cog"></i> <?=_('Helpers')?></span>
            </summary>

            <div class="chatai-obslog">
                <button type="button"
                        class="chatai-dashboard-export-csv ui-button chatai-dashboard-secondary-button">
                    <i class="fa fa-download"></i> <?= __('Download CSV') ?>
                </button>

                <div class="chatai-prune">
                    <button type="button"
                            class="chatai-prune-obslog ui-button chatai-dashboard-secondary-button"
                            popovertarget="chatai-prune-pop"
                            popovertargetaction="show">
                        <i class="fa fa-cut"></i> <?= __('Prune Observation Log') ?>
                    </button>

                </div>
            </div>
        </details>
        <div id="chatai-prune-pop" popover>
            <p class="chatai-dashboard-muted chatai-dashboard-sub">
                <?=_('Enter the number of days to retain or Esc to cancel')?>
            </p>
            <input
                    type="number"
                    name="prune_retain_days"
                    value=""
                    min="1"
                    max="3650"
                    class="uk-input"
            >
            <button
                    type="submit"
                    value="1"
                    name="submit_prune_obslog"
                    class="chatai-prune-obslog ui-button chatai-dashboard-secondary-button ui-state-default"
            >
                <i class="fa fa-cut"></i> <?= __('Chop the log') ?>
            </button>
        </div>

    </section>

</div>





