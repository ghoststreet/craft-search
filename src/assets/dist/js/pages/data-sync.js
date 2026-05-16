(function () {
    'use strict';

    var ns = window.CraftSearch;
    var DOM = ns.core.DOM;

    function startPolling() {
        var progress = DOM.find('reindex-progress');
        var btn = DOM.findControl('reindex-btn');
        if (progress) progress.hidden = false;
        if (btn) btn.disabled = true;

        var peakEntries = 0;
        var peakChunks = 0;

        var interval = setInterval(function () {
            Craft.sendActionRequest('POST', 'ai-search/data-sync/get-stats')
                .then(function (response) {
                    var data = response.data;
                    if (!data || !data.success) return;

                    var entries = DOM.find('progress-entries');
                    var chunks = DOM.find('progress-chunks');
                    var queue = DOM.find('progress-queue');
                    var statEntries = DOM.find('stat-entries');
                    var statChunks = DOM.find('stat-chunks');

                    if (data.queueRemaining === 0) {
                        clearInterval(interval);
                        if (entries) entries.textContent = data.entryCount.toLocaleString();
                        if (chunks) chunks.textContent = data.chunkCount.toLocaleString();
                        if (statEntries) statEntries.textContent = data.entryCount.toLocaleString();
                        if (statChunks) statChunks.textContent = data.chunkCount.toLocaleString();
                        if (progress) {
                            progress.innerHTML = '<div class="pane"><p><strong>Sync complete.</strong></p></div>';
                        }
                        if (btn) btn.disabled = false;
                        return;
                    }

                    peakEntries = Math.max(peakEntries, data.entryCount);
                    peakChunks = Math.max(peakChunks, data.chunkCount);

                    if (entries) entries.textContent = peakEntries.toLocaleString();
                    if (chunks) chunks.textContent = peakChunks.toLocaleString();
                    if (queue) queue.textContent = data.queueRemaining.toLocaleString();
                    if (statEntries) statEntries.textContent = peakEntries.toLocaleString();
                    if (statChunks) statChunks.textContent = peakChunks.toLocaleString();
                })
                .catch(function () {});
        }, 3000);
    }

    ns.pages.dataSync = {
        init: function () {
            var progress = DOM.find('reindex-progress');
            if (!progress) return;

            if (progress.getAttribute('data-craftsearch-sync-started') === '1') {
                startPolling();
                return;
            }

            Craft.sendActionRequest('POST', 'ai-search/data-sync/get-stats')
                .then(function (response) {
                    if (response.data && response.data.success && response.data.queueRemaining > 0) {
                        startPolling();
                    }
                })
                .catch(function () {});
        }
    };

    DOM.ready(ns.pages.dataSync.init);
})();
