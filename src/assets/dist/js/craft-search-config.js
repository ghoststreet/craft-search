(function () {
    'use strict';
    var ns = window.CraftSearch;
    var root = document.querySelector('[data-craftsearch-config]');
    var parsed = {};
    if (root) {
        try { parsed = JSON.parse(root.getAttribute('data-craftsearch-config')) || {}; }
        catch (e) { parsed = {}; }
    }
    ns.config = Object.assign({
        csrfTokenName: (window.Craft && Craft.csrfTokenName) || null,
        csrfTokenValue: (window.Craft && Craft.csrfTokenValue) || null,
        actionUrl: (window.Craft && Craft.getActionUrl) ? Craft.getActionUrl.bind(Craft) : null
    }, parsed);
})();
