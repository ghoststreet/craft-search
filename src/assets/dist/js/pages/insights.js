(function () {
    'use strict';
    var ns = window.CraftSearch;

    ns.pages.insights = {
        init: function () {
            if (typeof Chart !== 'undefined' && ns.components.Chart) {
                ns.core.ChartTheme.applyChartDefaults();
                ns.components.Chart.buildAll();
            }
        }
    };

    ns.core.DOM.ready(ns.pages.insights.init);
})();
