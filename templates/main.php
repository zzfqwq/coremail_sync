<?php

$l = $_['l10n'];

\OCP\Util::addTranslations('coremail_sync');
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
        <button class="cmsync-primary" type="button" data-action="sync"><?php p($l->t('Sync now')); ?></button>
        <nav class="cmsync-nav" aria-label="<?php p($l->t('Coremail sync')); ?>">
            <button class="cmsync-nav-item active" type="button"><?php p($l->t('Sync bridge')); ?></button>
        </nav>
        <div class="cmsync-account">
            <div class="cmsync-account-name" data-field="accountName"><?php p($l->t('Not configured')); ?></div>
            <button class="cmsync-more" type="button" data-action="settings" aria-label="<?php p($l->t('Settings')); ?>">...</button>
        </div>
        <div class="cmsync-side-footer">
            <button class="cmsync-link" type="button" data-action="settings"><?php p($l->t('Settings')); ?></button>
        </div>
    </aside>

    <main class="cmsync-main">
        <header class="cmsync-toolbar">
            <h1>Coremail Sync</h1>
            <button class="cmsync-icon" type="button" data-action="sync"><?php p($l->t('Sync now')); ?></button>
        </header>

        <section class="cmsync-status" data-field="status"></section>

        <section class="cmsync-grid" aria-label="<?php p($l->t('Sync summary')); ?>">
            <article class="cmsync-panel">
                <h2><?php p($l->t('Direction')); ?></h2>
                <strong>Coremail -&gt; Nextcloud</strong>
                <p><?php p($l->t('Contacts and calendars are written into native Nextcloud address books and calendars.')); ?></p>
            </article>
            <article class="cmsync-panel">
                <h2><?php p($l->t('Schedule')); ?></h2>
                <strong data-field="intervalLabel"><?php p($l->t('Every %s minutes', [30])); ?></strong>
                <p><?php p($l->t('Nextcloud cron runs the same sync service used by manual sync.')); ?></p>
            </article>
            <article class="cmsync-panel">
                <h2><?php p($l->t('Last run')); ?></h2>
                <strong data-field="lastRun"><?php p($l->t('Never')); ?></strong>
                <p data-field="lastSummary"><?php p($l->t('No sync has run yet.')); ?></p>
            </article>
            <article class="cmsync-panel">
                <h2><?php p($l->t('Mode')); ?></h2>
                <strong data-field="modeLabel"><?php p($l->t('Production')); ?></strong>
                <p data-field="davBaseUrlLabel"><?php p($l->t('Coremail DAV URL is not configured.')); ?></p>
            </article>
        </section>

        <section class="cmsync-results" data-field="results"></section>
    </main>

    <div class="cmsync-drawer" data-field="settingsDrawer" hidden>
        <form class="cmsync-form" data-field="settingsForm">
            <div class="cmsync-drawer-head">
                <h2><?php p($l->t('Sync settings')); ?></h2>
                <button type="button" class="cmsync-close" data-action="closeSettings" aria-label="<?php p($l->t('Close')); ?>">&times;</button>
            </div>
            <label>
                <span><?php p($l->t('Coremail account')); ?></span>
                <input name="email" type="email" autocomplete="username" required>
            </label>
            <label>
                <span><?php p($l->t('Client password')); ?></span>
                <input name="password" type="password" autocomplete="current-password" placeholder="<?php p($l->t('Leave blank to keep saved password')); ?>">
            </label>
            <label>
                <span><?php p($l->t('Coremail DAV URL')); ?></span>
                <input name="davBaseUrl" type="text" placeholder="<?php p($l->t('Use admin default if blank')); ?>">
            </label>
            <section class="cmsync-admin-settings" data-field="adminSettings" hidden>
                <h3><?php p($l->t('Admin deployment mode')); ?></h3>
                <label>
                    <span><?php p($l->t('Security mode')); ?></span>
                    <select name="securityMode">
                        <option value="production"><?php p($l->t('Production / public')); ?></option>
                        <option value="demo">Demo / VM</option>
                    </select>
                </label>
                <label>
                    <span><?php p($l->t('Default Coremail DAV URL')); ?></span>
                    <input name="adminDavBaseUrl" type="text" placeholder="https://mail.example.com/coremail/dav">
                </label>
            </section>
            <div class="cmsync-checks">
                <label>
                    <input name="syncContacts" type="checkbox" value="1" checked>
                    <span><?php p($l->t('Contacts')); ?></span>
                </label>
                <label>
                    <input name="syncCalendars" type="checkbox" value="1" checked>
                    <span><?php p($l->t('Calendars')); ?></span>
                </label>
            </div>
            <label>
                <span><?php p($l->t('Cron interval minutes')); ?></span>
                <input name="intervalMinutes" type="number" min="1" max="1440" value="30" required>
            </label>
            <p class="cmsync-help"><?php p($l->t('Current version is one-way: changes in Coremail are synchronized to native Nextcloud Calendar and Contacts.')); ?></p>
            <div class="cmsync-actions">
                <button class="primary" type="submit"><?php p($l->t('Save')); ?></button>
                <button type="button" data-action="closeSettings"><?php p($l->t('Cancel')); ?></button>
            </div>
        </form>
    </div>
</div>
