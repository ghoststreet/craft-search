(function () {
    'use strict';
    var ns = window.CraftSearch;

    function csrfHeaders() {
        var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        if (ns.config.csrfTokenName && ns.config.csrfTokenValue) {
            headers['X-CSRF-Token'] = ns.config.csrfTokenValue;
        }
        return headers;
    }

    ns.core.API = {
        get: function (url) {
            return fetch(url, { method: 'GET', credentials: 'same-origin', headers: csrfHeaders() })
                .then(function (r) { return r.json(); });
        },
        post: function (url, body) {
            var headers = Object.assign({ 'Content-Type': 'application/json' }, csrfHeaders());
            return fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: headers,
                body: JSON.stringify(body || {})
            }).then(function (r) { return r.json(); });
        },
        action: function (path, body) {
            var url = ns.config.actionUrl ? ns.config.actionUrl(path) : path;
            return this.post(url, body);
        }
    };
})();
