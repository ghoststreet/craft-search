(function () {
    'use strict';
    var Theme = window.CraftSearch.core.ChartTheme;

    function sparklineConfig(series, color) {
        var p = Theme.palette();
        var c = color || p.primary;
        return {
            type: 'line',
            data: {
                labels: series.map(function (r) { return r.date; }),
                datasets: [{
                    data: series.map(function (r) { return r.value; }),
                    borderColor: c,
                    backgroundColor: c.replace('rgb', 'rgba').replace(')', ', 0.15)'),
                    borderWidth: 1.5,
                    pointRadius: 0,
                    tension: 0.3,
                    fill: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { enabled: true, intersect: false, mode: 'index' } },
                scales: { x: { display: false }, y: { display: false, beginAtZero: true } }
            }
        };
    }

    function areaConfig(series) {
        var p = Theme.palette();
        var cfg = sparklineConfig(series, p.primary);
        cfg.data.datasets[0].fill = true;
        cfg.data.datasets[0].backgroundColor = p.primarySoft;
        return cfg;
    }

    function durationConfig(series) {
        var p = Theme.palette();
        var cfg = sparklineConfig(series.map(function (r) { return { date: r.date, value: r.avg }; }), p.primary);
        if (series.some(function (r) { return r.p95 != null; })) {
            cfg.data.datasets.push({
                data: series.map(function (r) { return r.p95; }),
                borderColor: p.warn,
                borderWidth: 1,
                borderDash: [4, 3],
                pointRadius: 0,
                tension: 0.3,
                fill: false
            });
        }
        return cfg;
    }

    function donutConfig(parts) {
        var p = Theme.palette();
        return {
            type: 'doughnut',
            data: {
                labels: ['Indexed', 'Stale', 'Not indexed'],
                datasets: [{
                    data: [parts.indexed, parts.stale, parts.notIndexed],
                    backgroundColor: [p.success, p.stale, p.unindexed],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: { legend: { display: false }, tooltip: { enabled: true } }
            }
        };
    }

    function stackedBarConfig(data) {
        var p = Theme.palette();
        return {
            type: 'bar',
            data: {
                labels: data.map(function (r) { return r.site; }),
                datasets: [
                    { label: 'Indexed', data: data.map(function (r) { return r.indexed; }), backgroundColor: p.success },
                    { label: 'Stale', data: data.map(function (r) { return r.stale; }), backgroundColor: p.stale },
                    { label: 'Not indexed', data: data.map(function (r) { return r.notIndexed; }), backgroundColor: p.unindexed }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10 } } },
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, grid: { color: p.grid }, beginAtZero: true }
                }
            }
        };
    }

    var BUILDERS = {
        'sparkline': sparklineConfig,
        'area': areaConfig,
        'duration': durationConfig,
        'donut': donutConfig,
        'stacked-bar': stackedBarConfig
    };

    window.CraftSearch.components.Chart = {
        build: function (canvas) {
            if (typeof Chart === 'undefined') return null;
            var kind = canvas.getAttribute('data-craftsearch-chart');
            var builder = BUILDERS[kind];
            if (!builder) return null;
            var series = window.CraftSearch.core.Utils.parseJSON(
                canvas.getAttribute('data-craftsearch-series'),
                null
            );
            if (series == null) return null;
            return new Chart(canvas.getContext('2d'), builder(series));
        },
        buildAll: function (root) {
            var canvases = (root || document).querySelectorAll('canvas[data-craftsearch-chart]');
            return Array.prototype.map.call(canvases, this.build, this);
        }
    };
})();
