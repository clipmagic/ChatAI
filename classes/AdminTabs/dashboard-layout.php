<?php namespace ProcessWire;

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
    'requestsLabel' => __('Requests'),
    'todayLabel'    => __('Today'),
    'last7Label'    => __('Last 7 days'),
    'last30Label'   => __('Last 30 days'),
    'volumeUrl'     => $page->url . '?api=volumeData',
    'insightsUrl'     => $page->url . '?api=insights',
    'obslogExportUrl' => $page->url . '?api=obslogExport'
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
                <h3><?=__('Chats (30 days)');?></h3>
                <p class="chatai-dashboard-kpi-value">0</p>
                <p class="chatai-dashboard-kpi-note"><?=('Awaiting data')?></p>
            </article>
            <article class="chatai-dashboard-kpi">
                <h3><?=__('Messages')?></h3>
                <p class="chatai-dashboard-kpi-value">0</p>
                <p class="chatai-dashboard-kpi-note"><?=__('User and assistant')?></p>
            </article>
            <article class="chatai-dashboard-kpi">
                <h3><?=__('Errors / Cutoffs')?></h3>
                <p class="chatai-dashboard-kpi-value">0</p>
                <p class="chatai-dashboard-kpi-note"><?=$dashboardConfig['last30Label']?></p>
            </article>
            <article class="chatai-dashboard-kpi">
                <h3><?=__('Blocked / Rate limited')?></h3>
                <p class="chatai-dashboard-kpi-value">0</p>
                <p class="chatai-dashboard-kpi-note"><?=__('Blacklist and limits')?></p>
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
    <button type="button"
            class="chatai-dashboard-export-csv ui-button chatai-dashboard-secondary-button">
        <?= __('Export CSV') ?>
    </button>
</div>




