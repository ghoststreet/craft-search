(function() {
    function handleWipeClick(e) {
        const btn = e.target.closest('.wipe-reindex-btn');
        if (!btn) return;

        e.preventDefault();
        e.stopPropagation();

        const confirmMessage = Craft.t('ai-search', 'Are you sure you want to wipe the database and re-index all entries? This action cannot be undone.');
        if (!confirm(confirmMessage)) {
            return false;
        }

        const actionUrl = btn.getAttribute('data-action-url');
        const csrfName = btn.getAttribute('data-csrf-name');
        const csrfValue = btn.getAttribute('data-csrf-value');

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = actionUrl;
        form.style.display = 'none';

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'ai-search/settings/wipe-and-reindex';
        form.appendChild(actionInput);

        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = csrfName;
        csrfInput.value = csrfValue;
        form.appendChild(csrfInput);

        document.body.appendChild(form);
        form.submit();
    }

    function initWipeButton() {
        document.removeEventListener('click', handleWipeClick);
        document.addEventListener('click', handleWipeClick, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWipeButton);
    } else {
        setTimeout(initWipeButton, 500);
    }
})();

