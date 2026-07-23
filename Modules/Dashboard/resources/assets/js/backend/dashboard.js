/**
 * Backend dashboard charts (Chart.js 2.x).
 *
 * Data is rendered by the Blade view into #dashboard-chart-data as JSON, so
 * this file can be loaded before the markup without needing inline globals.
 */
(function ($) {
    'use strict';

    var gridColor = 'rgba(255, 255, 255, .08)';
    var tickColor = '#9a9a9a';

    function readChartData() {
        var holder = document.getElementById('dashboard-chart-data');
        if (!holder) {
            return null;
        }
        try {
            return JSON.parse(holder.textContent || holder.innerText);
        } catch (e) {
            return null;
        }
    }

    function hexToRgba(hex, alpha) {
        var value = hex.replace('#', '');
        var r = parseInt(value.substring(0, 2), 16);
        var g = parseInt(value.substring(2, 4), 16);
        var b = parseInt(value.substring(4, 6), 16);
        return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
    }

    function renderTrendChart(trend) {
        var canvas = document.getElementById('dashboardTrendChart');
        if (!canvas || !trend || !trend.datasets || !trend.datasets.length) {
            return;
        }

        new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: trend.labels,
                datasets: trend.datasets.map(function (set) {
                    return {
                        label: set.label,
                        data: set.data,
                        borderColor: set.color,
                        backgroundColor: hexToRgba(set.color, 0.12),
                        pointBackgroundColor: set.color,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        borderWidth: 2,
                        lineTension: 0.35,
                        fill: true
                    };
                })
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        boxWidth: 12,
                        fontColor: tickColor,
                        usePointStyle: true
                    }
                },
                tooltips: {
                    mode: 'index',
                    intersect: false
                },
                scales: {
                    xAxes: [{
                        gridLines: { color: gridColor, drawBorder: false },
                        ticks: { fontColor: tickColor }
                    }],
                    yAxes: [{
                        gridLines: { color: gridColor, drawBorder: false },
                        ticks: { fontColor: tickColor, beginAtZero: true, precision: 0 }
                    }]
                }
            }
        });
    }

    function renderStatusChart(status) {
        var canvas = document.getElementById('dashboardStatusChart');
        if (!canvas || !status || !status.data || !status.data.length) {
            return;
        }

        new Chart(canvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: status.labels,
                datasets: [{
                    data: status.data,
                    backgroundColor: status.colors,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutoutPercentage: 70,
                legend: { display: false }
            }
        });
    }

    $(document).ready(function () {
        if (typeof Chart === 'undefined') {
            return;
        }

        var payload = readChartData();
        if (!payload) {
            return;
        }

        renderTrendChart(payload.trend);
        renderStatusChart(payload.status);
    });
})(jQuery);
