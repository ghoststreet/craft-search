(function () {
    'use strict';
    window.SmartSearch = window.SmartSearch || {};
    window.SmartSearch.core = window.SmartSearch.core || {};
    window.SmartSearch.components = window.SmartSearch.components || {};
    window.SmartSearch.pages = window.SmartSearch.pages || {};
    window.SmartSearch.config = window.SmartSearch.config || {};

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
