(function () {
    'use strict';
    window.SmartSearch.core.Utils = {
        debounce: function (fn, wait) {
            var timer;
            return function () {
                var ctx = this, args = arguments;
                clearTimeout(timer);
                timer = setTimeout(function () { fn.apply(ctx, args); }, wait);
            };
        },
        escape: function (str) {
            var div = document.createElement('div');
            div.textContent = String(str == null ? '' : str);
            return div.innerHTML;
        },
        parseJSON: function (str, fallback) {
            if (!str) return fallback;
            try { return JSON.parse(str); } catch (e) { return fallback; }
        }
    };
})();
