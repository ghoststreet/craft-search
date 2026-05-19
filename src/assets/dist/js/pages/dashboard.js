(function () {
    'use strict';
    var ns = window.SmartSearch;

    ns.pages.dashboard = {
        init: function () {
            if (typeof Chart === 'undefined') return;
            ns.core.ChartTheme.applyChartDefaults();
            ns.components.Chart.buildAll();
        }
    };

    ns.core.DOM.ready(ns.pages.dashboard.init);
})();
