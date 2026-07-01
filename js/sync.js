(function () {
    'use strict';

    const root = document.getElementById('coremail-sync-app');
    if (!root) {
        return;
    }

    const endpoint = (path) => OC.generateUrl('/apps/coremail_sync' + path);
    const q = (selector) => root.querySelector(selector);
    const qa = (selector) => Array.from(root.querySelectorAll(selector));

    const state = {
        settings: null,
    };

    async function requestJson(url, options) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                requesttoken: OC.requestToken,
            },
            ...options,
        });
        const payload = await response.json().catch(() => ({}));
        return { response, payload };
    }

    function setStatus(message, tone) {
        const node = q('[data-field="status"]');
        if (!node) {
            return;
        }
        node.textContent = message || '';
        node.dataset.tone = tone || '';
    }

    function fillSettings(settings) {
        state.settings = settings;
        const form = q('[data-field="settingsForm"]');
        if (form) {
            form.email.value = settings.email || root.dataset.email || '';
            form.password.value = '';
            form.davBaseUrl.value = settings.davBaseUrl || '';
            form.syncContacts.checked = settings.syncContacts !== false;
            form.syncCalendars.checked = settings.syncCalendars !== false;
            form.intervalMinutes.value = settings.intervalMinutes || 30;
        }

        const accountName = q('[data-field="accountName"]');
        if (accountName) {
            accountName.textContent = settings.email || 'Not configured';
        }

        const intervalLabel = q('[data-field="intervalLabel"]');
        if (intervalLabel) {
            intervalLabel.textContent = `Every ${settings.intervalMinutes || 30} minutes`;
        }

        const lastRun = q('[data-field="lastRun"]');
        if (lastRun) {
            lastRun.textContent = settings.lastRun || 'Never';
        }

        const lastSummary = q('[data-field="lastSummary"]');
        if (lastSummary) {
            lastSummary.textContent = settings.lastSummary || 'No sync has run yet.';
        }

        const modeLabel = q('[data-field="modeLabel"]');
        if (modeLabel) {
            modeLabel.textContent = settings.mode === 'demo' ? 'Demo / VM' : 'Production';
        }

        const davBaseUrlLabel = q('[data-field="davBaseUrlLabel"]');
        if (davBaseUrlLabel) {
            davBaseUrlLabel.textContent = settings.effectiveDavBaseUrl || 'Coremail DAV URL is not configured.';
        }
    }

    function fillAdminSettings(payload) {
        const form = q('[data-field="settingsForm"]');
        const section = q('[data-field="adminSettings"]');
        const canManage = payload.canManageAdminSettings === true;
        if (section) {
            section.hidden = !canManage;
        }
        if (!form || !canManage) {
            return;
        }

        const adminSettings = payload.adminSettings || {};
        form.securityMode.value = adminSettings.mode || 'production';
        form.adminDavBaseUrl.value = adminSettings.davBaseUrl || '';
    }

    function openSettings() {
        const drawer = q('[data-field="settingsDrawer"]');
        if (drawer) {
            drawer.hidden = false;
        }
    }

    function closeSettings() {
        const drawer = q('[data-field="settingsDrawer"]');
        if (drawer) {
            drawer.hidden = true;
        }
    }

    function settingsPayload(form) {
        const data = new FormData(form);
        return {
            email: String(data.get('email') || '').trim(),
            password: String(data.get('password') || ''),
            davBaseUrl: String(data.get('davBaseUrl') || '').trim(),
            securityMode: String(data.get('securityMode') || 'production'),
            adminDavBaseUrl: String(data.get('adminDavBaseUrl') || '').trim(),
            syncContacts: data.get('syncContacts') === '1',
            syncCalendars: data.get('syncCalendars') === '1',
            intervalMinutes: Number(data.get('intervalMinutes') || 30),
        };
    }

    async function loadSettings() {
        const { response, payload } = await requestJson(endpoint('/api/settings'));
        if (!response.ok || !payload.ok) {
            setStatus(payload.message || 'Unable to load sync settings.', 'error');
            openSettings();
            return;
        }

        fillSettings(payload.settings || {});
        fillAdminSettings(payload);
        setStatus(payload.settings?.configured ? 'Ready to sync Coremail into Nextcloud.' : 'Please configure the Coremail account before syncing.', payload.settings?.configured ? 'success' : 'warn');
        if (!payload.settings?.configured) {
            openSettings();
        }
    }

    async function saveSettings(form) {
        setStatus('Saving settings...', 'info');
        const { response, payload } = await requestJson(endpoint('/api/settings'), {
            method: 'PUT',
            body: JSON.stringify(settingsPayload(form)),
        });
        if (!response.ok || !payload.ok) {
            setStatus(payload.message || 'Saving settings failed.', 'error');
            return;
        }

        fillSettings(payload.settings || {});
        fillAdminSettings(payload);
        closeSettings();
        setStatus('Settings saved.', 'success');
    }

    function renderResults(result) {
        const box = q('[data-field="results"]');
        if (!box) {
            return;
        }
        const sections = [
            ['Contacts', result.contacts],
            ['Calendars', result.calendars],
        ];
        box.innerHTML = sections.map(([label, item]) => {
            const ok = item?.ok === true;
            const count = item?.count ?? 0;
            const message = item?.message || (ok ? `${count} item(s) synchronized.` : 'Not run.');
            const attempts = Array.isArray(item?.attempts) && item.attempts.length > 0
                ? `<pre>${escapeHtml(item.attempts.map((attempt) => `${attempt.status || 0} ${attempt.depth || '-'} ${attempt.url || ''}`).join('\n'))}</pre>`
                : '';
            return `
                <article class="cmsync-result">
                    <strong>${escapeHtml(label)}: ${ok ? 'OK' : 'Skipped or failed'}</strong>
                    <p>${escapeHtml(message)}</p>
                    ${attempts}
                </article>
            `;
        }).join('');
    }

    async function syncNow() {
        setStatus('Synchronizing Coremail contacts and calendars...', 'info');
        const { response, payload } = await requestJson(endpoint('/api/sync'), {
            method: 'POST',
            body: JSON.stringify({}),
        });
        if (!response.ok || !payload.ok) {
            setStatus(payload.message || 'Sync failed.', 'error');
            if (payload.result) {
                renderResults(payload.result);
            }
            return;
        }

        renderResults(payload.result || {});
        fillSettings(payload.settings || state.settings || {});
        fillAdminSettings(payload);
        setStatus(payload.message || 'Sync completed.', 'success');
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function bindActions() {
        qa('[data-action="settings"]').forEach((button) => button.addEventListener('click', openSettings));
        qa('[data-action="closeSettings"]').forEach((button) => button.addEventListener('click', closeSettings));
        qa('[data-action="sync"]').forEach((button) => button.addEventListener('click', syncNow));

        const form = q('[data-field="settingsForm"]');
        form?.addEventListener('submit', (event) => {
            event.preventDefault();
            saveSettings(form);
        });
    }

    bindActions();
    loadSettings();
})();
