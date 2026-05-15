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

    function parse(r) {
        return r.json().catch(function () { return {}; }).then(function (body) {
            if (!r.ok || body.success === false) {
                var err = new Error(body.code || 'UNKNOWN');
                err.code = body.code || 'UNKNOWN';
                err.requestId = body.requestId || null;
                err.retryAfter = body.retryAfter || null;
                err.status = r.status;
                throw err;
            }
            return body;
        });
    }

    ns.core.API = {
        get: function (url) {
            return fetch(url, { method: 'GET', credentials: 'same-origin', headers: csrfHeaders() })
                .then(parse);
        },
        post: function (url, body) {
            var headers = Object.assign({ 'Content-Type': 'application/json' }, csrfHeaders());
            return fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: headers,
                body: JSON.stringify(body || {})
            }).then(parse);
        },
        action: function (path, body) {
            var url = ns.config.actionUrl ? ns.config.actionUrl(path) : path;
            return this.post(url, body);
        }
    };
})();
