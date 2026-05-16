(function () {
    'use strict';
    window.CraftSearch = window.CraftSearch || {};
    window.CraftSearch.core = window.CraftSearch.core || {};
    window.CraftSearch.components = window.CraftSearch.components || {};
    window.CraftSearch.pages = window.CraftSearch.pages || {};
    window.CraftSearch.config = window.CraftSearch.config || {};

    var CONFIRM_ATTR = 'data-craftsearch-confirm';

    function handleConfirm(event) {
        var el = event.target.closest('[' + CONFIRM_ATTR + ']');
        if (!el) return;
        var message = el.getAttribute(CONFIRM_ATTR);
        if (message && !window.confirm(message)) {
            event.preventDefault();
            event.stopPropagation();
        }
    }

    document.addEventListener('submit', handleConfirm, true);
    document.addEventListener('click', function (event) {
        var el = event.target.closest('[' + CONFIRM_ATTR + ']');
        if (!el || el.closest('form')) return;
        var message = el.getAttribute(CONFIRM_ATTR);
        if (message && !window.confirm(message)) {
            event.preventDefault();
            event.stopPropagation();
        }
    }, true);
})();
