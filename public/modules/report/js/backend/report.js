/**
 * Report charts (Chart.js 2.x from the Theme vendor bundle) and the report filter bar.
 *
 * Each chart's data is rendered by Blade into a <script type="application/json" data-report-chart>
 * next to its canvas: {id, type: line|bar|horizontalBar, format: money|count|percent, labels,
 * series: [{label, data, color, previous}], stacked}. Colours come from the server (fixed palette
 * slots), so a series keeps its colour whatever else is on the chart. Every chart also has a
 * table view in the page.
 */
(function () {
    'use strict';

    var ink = '#4b5563';
    var grid = '#eceef1';
    var axis = '#d5d9df';
    var surface = '#ffffff';
    var number = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 });
    var decimal = new Intl.NumberFormat('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });

    function formatValue(value, format, precise) {
        if (value === null || value === undefined || isNaN(value)) {
            return '—';
        }
        if (format === 'money') {
            return '₹' + (precise ? decimal : number).format(value);
        }
        if (format === 'percent') {
            return (Math.round(value * 10) / 10) + '%';
        }
        return number.format(value);
    }

    function compactMoney(value) {
        var abs = Math.abs(value);
        if (abs >= 10000000) {
            return '₹' + decimal.format(value / 10000000) + ' Cr';
        }
        if (abs >= 100000) {
            return '₹' + decimal.format(value / 100000) + ' L';
        }
        if (abs >= 1000) {
            return '₹' + decimal.format(value / 1000) + 'k';
        }
        return '₹' + number.format(value);
    }

    function tickFormatter(format) {
        return function (value) {
            if (format === 'money') {
                return compactMoney(value);
            }
            return formatValue(value, format, false);
        };
    }

    function dataset(series, payload) {
        var isLine = payload.type === 'line';
        var many = payload.labels.length > 31;

        if (isLine) {
            return {
                label: series.label,
                data: series.data,
                borderColor: series.color,
                backgroundColor: series.color,
                pointBackgroundColor: series.color,
                pointBorderColor: surface,
                pointBorderWidth: 2,
                pointRadius: many ? 0 : 3,
                pointHoverRadius: 5,
                pointHitRadius: 12,
                borderWidth: series.previous ? 1.5 : 2,
                lineTension: 0.2,
                fill: false,
                spanGaps: true
            };
        }

        return {
            label: series.label,
            data: series.data,
            backgroundColor: series.color,
            hoverBackgroundColor: series.color,
            borderColor: surface,
            borderWidth: payload.stacked ? 1 : 0
        };
    }

    function render(payload) {
        var canvas = document.getElementById(payload.id);
        if (!canvas || !payload.series || !payload.series.length) {
            return;
        }

        var horizontal = payload.type === 'horizontalBar';
        var valueTicks = { fontColor: ink, beginAtZero: true, callback: tickFormatter(payload.format), maxTicksLimit: 6 };
        var categoryTicks = { fontColor: ink, autoSkip: true, maxRotation: 0, autoSkipPadding: 12 };
        var valueAxis = { stacked: !!payload.stacked, gridLines: { color: grid, zeroLineColor: axis, drawBorder: false }, ticks: valueTicks };
        var categoryAxis = { stacked: !!payload.stacked, gridLines: { display: false, drawBorder: false }, ticks: categoryTicks, barPercentage: 0.7, categoryPercentage: 0.8 };

        new Chart(canvas.getContext('2d'), {
            type: payload.type,
            data: {
                labels: payload.labels,
                datasets: payload.series.map(function (series) { return dataset(series, payload); })
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 400 },
                legend: {
                    display: payload.series.length > 1,
                    position: 'top',
                    align: 'start',
                    labels: { boxWidth: 10, fontColor: ink, usePointStyle: true, padding: 16 }
                },
                tooltips: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function (item, data) {
                            var set = data.datasets[item.datasetIndex];
                            var value = horizontal ? item.xLabel : item.yLabel;
                            return ' ' + set.label + ': ' + formatValue(value, payload.format, true);
                        }
                    }
                },
                hover: { mode: 'index', intersect: false },
                scales: {
                    xAxes: [horizontal ? valueAxis : categoryAxis],
                    yAxes: [horizontal ? categoryAxis : valueAxis]
                }
            }
        });
    }

    function initCharts() {
        if (typeof Chart === 'undefined') {
            return;
        }

        var holders = document.querySelectorAll('script[data-report-chart]');
        for (var i = 0; i < holders.length; i++) {
            try {
                render(JSON.parse(holders[i].textContent));
            } catch (e) {
                // A broken payload leaves the table view in place.
            }
        }
    }

    /**
     * The server uses the dates only with the "Custom range" preset, so editing a date selects it.
     */
    function initFilters() {
        var forms = document.querySelectorAll('[data-report-filters]');
        for (var i = 0; i < forms.length; i++) {
            (function (form) {
                var preset = form.querySelector('[data-report-preset]');
                var inputs = form.querySelectorAll('[data-report-custom] input');
                if (!preset) {
                    return;
                }

                for (var k = 0; k < inputs.length; k++) {
                    inputs[k].addEventListener('change', function () {
                        preset.value = 'custom';
                    });
                }
            })(forms[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { initCharts(); initFilters(); });
    } else {
        initCharts();
        initFilters();
    }
})();
