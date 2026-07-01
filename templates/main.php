<?php

script('coremail_sync', 'sync');
style('coremail_sync', 'sync');

?>
<div id="coremail-sync-app"
     class="cmsync-app"
     data-user-id="<?php p($_['userId']); ?>"
     data-display-name="<?php p($_['displayName']); ?>"
     data-email="<?php p($_['email']); ?>">
    <aside class="cmsync-side">
        <div class="cmsync-logo">Coremail</div>
        <button class="cmsync-primary" type="button" data-action="sync">Sync now</button>
        <nav class="cmsync-nav" aria-label="Coremail sync">
            <button class="cmsync-nav-item active" type="button">Sync bridge</button>
        </nav>
        <div class="cmsync-account">
            <div class="cmsync-account-name" data-field="accountName">Not configured</div>
            <button class="cmsync-more" type="button" data-action="settings" aria-label="settings">...</button>
        </div>
        <div class="cmsync-side-footer">
            <button class="cmsync-link" type="button" data-action="settings">Settings</button>
        </div>
    </aside>

    <main class="cmsync-main">
        <header class="cmsync-toolbar">
            <h1>Coremail Sync</h1>
            <button class="cmsync-icon" type="button" data-action="sync">Sync now</button>
        </header>

        <section class="cmsync-status" data-field="status"></section>

        <section class="cmsync-grid" aria-label="sync summary">
            <article class="cmsync-panel">
                <h2>Direction</h2>
                <strong>Coremail -&gt; Nextcloud</strong>
                <p>Contacts and calendars are written into native Nextcloud address books and calendars.</p>
            </article>
            <article class="cmsync-panel">
                <h2>Schedule</h2>
                <strong data-field="intervalLabel">Every 30 minutes</strong>
                <p>Nextcloud cron runs the same sync service used by manual sync.</p>
            </article>
            <article class="cmsync-panel">
                <h2>Last run</h2>
                <strong data-field="lastRun">Never</strong>
                <p data-field="lastSummary">No sync has run yet.</p>
            </article>
            <article class="cmsync-panel">
                <h2>Mode</h2>
                <strong data-field="modeLabel">Production</strong>
                <p data-field="davBaseUrlLabel">Coremail DAV URL is not configured.</p>
            </article>
        </section>

        <section class="cmsync-results" data-field="results"></section>
    </main>

    <div class="cmsync-drawer" data-field="settingsDrawer" hidden>
        <form class="cmsync-form" data-field="settingsForm">
            <div class="cmsync-drawer-head">
                <h2>Sync settings</h2>
                <button type="button" class="cmsync-close" data-action="closeSettings" aria-label="close">&times;</button>
            </div>
            <label>
                <span>Coremail account</span>
                <input name="email" type="email" autocomplete="username" required>
            </label>
            <label>
                <span>Client password</span>
                <input name="password" type="password" autocomplete="current-password" placeholder="Leave blank to keep saved password">
            </label>
            <label>
                <span>Coremail DAV URL</span>
                <input name="davBaseUrl" type="text" placeholder="Use admin default if blank">
            </label>
            <section class="cmsync-admin-settings" data-field="adminSettings" hidden>
                <h3>Admin deployment mode</h3>
                <label>
                    <span>Security mode</span>
                    <select name="securityMode">
                        <option value="production">Production / public</option>
                        <option value="demo">Demo / VM</option>
                    </select>
                </label>
                <label>
                    <span>Default Coremail DAV URL</span>
                    <input name="adminDavBaseUrl" type="text" placeholder="https://mail.example.com/coremail/dav">
                </label>
            </section>
            <div class="cmsync-checks">
                <label>
                    <input name="syncContacts" type="checkbox" value="1" checked>
                    <span>Contacts</span>
                </label>
                <label>
                    <input name="syncCalendars" type="checkbox" value="1" checked>
                    <span>Calendars</span>
                </label>
            </div>
            <label>
                <span>Cron interval minutes</span>
                <input name="intervalMinutes" type="number" min="1" max="1440" value="30" required>
            </label>
            <p class="cmsync-help">Current version is one-way: changes in Coremail are synchronized to native Nextcloud Calendar and Contacts.</p>
            <div class="cmsync-actions">
                <button class="primary" type="submit">Save</button>
                <button type="button" data-action="closeSettings">Cancel</button>
            </div>
        </form>
    </div>
</div>
