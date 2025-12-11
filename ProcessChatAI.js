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
    //var t = (window.ProcessWire && ProcessWire.config && ProcessWire.config.chataiDashboard) || {};

    var t = window.chatAIDashboard || {};


    // Range links
    var picker = document.querySelector('.chatai-range-picker');
    var rangeLinks = picker ? picker.querySelectorAll('.chatai-range-link') : null;

    // Volume chart
    var volumeCanvas = document.getElementById('chatai-chart-volume');
    var volumeChart = null;


    function loadVolumeData(days) {
        console.log('t in loadVolumeData: ', t)

        if (!t.volumeUrl || !volumeCanvas) return;

        var url = t.volumeUrl + '&days=' + encodeURIComponent(days);
        console.log('ChatAI: fetching volume data', { days: days, url: url });

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

    function reloadInsights(days) {
        if (!t.insightsUrl) return;

        var url = t.insightsUrl + '&days=' + encodeURIComponent(days);
        var body = document.getElementById('chatai-dashboard-insights-body');
        if (!body) return;

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
                // Donut chart and anything else can be hooked here later if needed
            });
        });

        // Initial load based on the currently active link
        var active = document.querySelector('.chatai-range-link.is-active');
        var initialDays = active ? parseInt(active.getAttribute('data-range'), 10) || 7 : 7;

        loadVolumeData(initialDays);
        reloadInsights(initialDays);
    }

    // Event types doughnut chart
    var eventsCanvas = document.getElementById('chatai-chart-events');
    if (hasChart && eventsCanvas) {
        var ctx = eventsCanvas.getContext('2d');

        var summary = window.chatAIEventSummary || {};
        var eventLabels = Object.keys(summary);
        var values = eventLabels.map(function (label) { return summary[label]; });

        // Fallback if no data yet
        var finalLabels = eventLabels.length ? eventLabels : ['no data'];
        var finalValues = eventLabels.length ? values : [1];

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: finalLabels,
                datasets: [{
                    data: finalValues
                    // backgroundColor etc. can be defined here if desired
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                },

                labels: {
                    // optional: map type keys to translated labels
                    generateLabels: function (chart) {
                        var data = chart.data;
                        if (!data.labels.length) {
                            return Chart.defaults.plugins.legend.labels.generateLabels(chart);
                        }
                        return data.labels.map(function (label, i) {
                            var text = label;
                            if (label === 'reply')   text = t.replyLabel   || 'Replies';
                            if (label === 'cutoff')  text = t.cutoffLabel  || 'Cutoffs';
                            if (label === 'blocked') text = t.blockedLabel || 'Blocked';

                            var style = Chart.defaults.plugins.legend.labels.generateLabels(chart)[i];
                            style.text = text;
                            return style;
                        });
                    }
                },
                cutout: '60%'
            }
        });
    }


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

