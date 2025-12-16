$(document).ready(function() {
    // instantiate WireTabs if defined
    if (typeof ProcessWire.config.JqueryWireTabs === 'object') {
        $('body.ProcessChatAI #pw-content-body').WireTabs({
            items: $(".WireTab"),
            id: 'chatai-tabs'
        });
    } else {
        console.log(typeof ProcessWire.config.JqueryWireTabs)
    }
});


/* Dashboard Components */

document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;
    var hasChart = (typeof Chart !== 'undefined');

    var eventsChart = null;
    var elEvents = document.getElementById('chatai-chart-events');


    var t = window.chatAIDashboard || {};


    // Range links
    var picker = document.querySelector('.chatai-range-picker');
    var rangeLinks = picker ? picker.querySelectorAll('.chatai-range-link') : null;

    // Volume chart
    var volumeCanvas = document.getElementById('chatai-chart-volume');
    var volumeChart = null;

    function reloadKpis(days) {
        if (!t.kpiUrl) return;

        var url = t.kpiUrl + '&days=' + encodeURIComponent(days);

        fetch(url, { credentials: 'same-origin' })
            .then(function(res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function(s) {
                console.log(s)

                if (!s) return;

                setText('kpi-chats', s.total_chats);
                setText('kpi-chats-note',  `in the last ${s.range_days} days`);

                setText('kpi-messages', s.total_messages);

                var ec = (parseInt(s.total_errors || 0, 10) + parseInt(s.total_cutoffs || 0, 10));
                setText('kpi-errors-cutoffs', ec);
                setText('kpi-errors-cutoffs-note', 'Errors: ' + (s.total_errors || 0) + ', cutoffs: ' + (s.total_cutoffs || 0));

                setText('kpi-blocked', s.total_blocked || 0);
                setText('kpi-blocked-note', 'Blocked: ' + (s.total_blocked || 0) + ', rate limited: ' + (s.rate_limited || 0));


                updateEventsChart({
                    reply:      s.total_replies,
                    cutoff:     s.total_cutoffs,
                    blocked:    s.total_blocked,
                    rate_limit: s.rate_limited
                });


            })
            .catch(function(err) {
                console.error('ChatAI: failed to load KPI snapshot:', err);
            });
    }

    function setText(id, v) {
        var el = document.getElementById(id);
        if (el) el.textContent = (v === null || typeof v === 'undefined') ? '' : String(v);
    }


    function loadVolumeData(days) {
        if (!t.volumeUrl || !volumeCanvas) return;
        var url = t.volumeUrl + '&days=' + encodeURIComponent(days);

        fetch(url, { credentials: 'same-origin' })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function (data) {
                if (!data || !data.labels || !data.totals) return;

                var ctx = volumeCanvas.getContext('2d');

                if (!volumeChart) {
                    volumeChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: t.requestsLabel || 'Requests',
                                data: data.totals,
                                tension: 0.25
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'bottom'
                                }
                            },
                            scales: {
                                x: { ticks: { maxRotation: 0 } },
                                y: {
                                    beginAtZero: true,
                                    ticks: { precision: 0 }
                                }
                            }
                        }
                    });
                } else {
                    volumeChart.data.labels = data.labels;
                    volumeChart.data.datasets[0].data = data.totals;
                    volumeChart.update();
                }
            })
            .catch(function (err) {
                console.error('ChatAI: failed to load volume data:', err);
            });
    }

    var eventsChart = null;

    function normalizeEventSummary(summary) {
        summary = summary || {};
        return {
            reply:      parseInt(summary.reply, 10) || 0,
            cutoff:     parseInt(summary.cutoff, 10) || 0,
            blocked:    parseInt(summary.blocked, 10) || 0,
            rate_limit: parseInt(summary.rate_limit, 10) || 0
        };
    }

    function mapEventLabel(key) {
        if (key === 'reply')      return t.replyLabel || 'Replies';
        if (key === 'cutoff')     return t.cutoffLabel || 'Cutoffs';
        if (key === 'blocked')    return t.blockedLabel || 'Blocked';
        if (key === 'rate_limit') return t.rateLimitedLabel || 'Rate limited';
        if (key === 'no_data')    return t.noDataLabel || 'No data';
        return key;
    }

    function updateEventsChart(summary) {
        if (typeof Chart === 'undefined') return;

        var eventsCanvas = document.getElementById('chatai-chart-events');
        if (!eventsCanvas) return;

        var ctx = eventsCanvas.getContext('2d');
        var s = normalizeEventSummary(summary);

        // Stable order
        var keys = ['reply', 'cutoff', 'blocked', 'rate_limit'];
        var values = keys.map(function (k) { return s[k] || 0; });

        var hasAny = values.some(function (v) { return v > 0; });

        // Use DISPLAY labels in chart.data.labels so the legend is correct with zero custom logic
        var finalKeys   = hasAny ? keys : ['no_data'];
        var finalLabels = finalKeys.map(function (k) { return mapEventLabel(k); });
        var finalValues = hasAny ? values : [1];

        if (!eventsChart) {
            eventsChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: finalLabels,
                    datasets: [{
                        data: finalValues
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: {
                        legend: {
                            display: true,
                            position: 'right'
                        },
                        tooltip: {
                            callbacks: {
                                // Keep tooltips tidy in the "no data" case
                                label: function (ctx) {
                                    if (!hasAny) return mapEventLabel('no_data');
                                    var label = (ctx.label != null) ? String(ctx.label) : '';
                                    var val = (ctx.raw != null) ? ctx.raw : '';
                                    return label + ': ' + val;
                                }
                            }
                        }
                    }
                }
            });
            return;
        }

        // Update existing
        eventsChart.data.labels = finalLabels;
        eventsChart.data.datasets[0].data = finalValues;
        eventsChart.update();
    }

    function reloadInsights(days) {
        if (!t.insightsUrl) return;

        var url = t.insightsUrl + '&days=' + encodeURIComponent(days);
        var body = document.querySelector('#chatai-dashboard-insights .InputfieldContent');        if (!body) return;

        fetch(url, { credentials: 'same-origin' })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function (data) {
                if (!data || typeof data.html !== 'string') return;
                body.innerHTML = data.html;
            })
            .catch(function (err) {
                console.error('ChatAI: failed to load insights:', err);
            });
    }

    function buildExportUrl(days) {
        if (!t.obslogExportUrl) return null;
        return t.obslogExportUrl + '&days=' + encodeURIComponent(days);
    }

    // Export CSV
    var exportBtn = document.querySelector('.chatai-dashboard-export-csv');
    if (exportBtn) {
        exportBtn.addEventListener('click', function (ev) {
            ev.preventDefault();
            var active = document.querySelector('.chatai-range-link.is-active');
            var days = active ? parseInt(active.getAttribute('data-range'), 10) || 7 : 7;

            var url = buildExportUrl(days);
            if (url) window.location.href = url;
        });
    }

    // Wire range links
    if (rangeLinks && rangeLinks.length) {
        rangeLinks.forEach(function (link) {
            link.addEventListener('click', function (ev) {
                ev.preventDefault();

                var days = parseInt(link.getAttribute('data-range'), 10) || 7;

                // active state
                rangeLinks.forEach(function (a) {
                    a.classList.remove('is-active');
                });
                link.classList.add('is-active');

                // reload range-dependent pieces
                loadVolumeData(days);
                reloadInsights(days);
                reloadKpis(days)
            });
        });

        // Initial load based on the currently active link
        var active = document.querySelector('.chatai-range-link.is-active');
        var initialDays = active ? parseInt(active.getAttribute('data-range'), 10) || 7 : 7;

        loadVolumeData(initialDays);
        reloadInsights(initialDays);
    }

    // Event types doughnut chart (initial render)
    updateEventsChart(window.chatAIEventSummary || {});

    // CSV export based on current range
    var exportBtn = document.querySelector('.chatai-dashboard-export-csv');
    if (exportBtn && t.obslogExportUrl) {
        exportBtn.addEventListener('click', function () {
            var days = 7;
            if (rangeSelect) {
                days = parseInt(rangeSelect.value, 10) || 7;
            }

            var url = t.obslogExportUrl + '&days=' + encodeURIComponent(days);
            window.location.href = url;
        });
    }
});

