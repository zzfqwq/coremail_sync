<?php

declare(strict_types=1);

namespace OCA\CoremailSync\Controller;

use OCA\CoremailSync\AppInfo\Application;
use OCA\CoremailSync\Service\SyncService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IGroupManager;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;

class SettingsController extends Controller {
    private const MODE_PRODUCTION = 'production';
    private const MODE_DEMO = 'demo';

    public function __construct(
        private IRequest $incomingRequest,
        private IConfig $config,
        private IUserSession $userSession,
        private IGroupManager $groupManager,
        private SyncService $syncService,
    ) {
        parent::__construct(Application::APP_ID, $incomingRequest);
    }

    /**
     * @NoAdminRequired
     */
    public function get(): DataResponse {
        $userId = $this->getUserId();

        return new DataResponse([
            'ok' => true,
            'settings' => $this->settingsForResponse($userId),
            'adminSettings' => $this->adminSettingsForResponse(),
            'canManageAdminSettings' => $this->isAdmin($userId),
        ]);
    }

    /**
     * @NoAdminRequired
     */
    public function save(): DataResponse {
        $userId = $this->getUserId();
        if ($userId === '') {
            return new DataResponse(['ok' => false, 'message' => 'Current Nextcloud user is not logged in.'], 401);
        }
        if ($this->isAdmin($userId)) {
            $this->saveAdminSettings();
        }

        $email = trim((string)$this->incomingRequest->getParam('email', ''));
        $password = (string)$this->incomingRequest->getParam('password', '');
        $davBaseUrl = trim((string)$this->incomingRequest->getParam('davBaseUrl', ''));
        $intervalMinutes = max(1, min(1440, (int)$this->incomingRequest->getParam('intervalMinutes', 30)));

        if ($email === '') {
            return new DataResponse(['ok' => false, 'message' => 'Coremail account is required.'], 400);
        }
        if ($davBaseUrl === '' && $this->adminDavBaseUrl() === '') {
            return new DataResponse(['ok' => false, 'message' => 'Coremail DAV URL is required.'], 400);
        }

        $this->config->setUserValue($userId, Application::APP_ID, 'email', $email);
        if ($password !== '') {
            $this->config->setUserValue($userId, Application::APP_ID, 'password', $password);
        }
        $this->config->setUserValue($userId, Application::APP_ID, 'dav_base_url', $davBaseUrl === '' ? '' : rtrim($davBaseUrl, '/'));
        $this->config->setUserValue($userId, Application::APP_ID, 'sync_contacts', $this->boolParam('syncContacts') ? '1' : '0');
        $this->config->setUserValue($userId, Application::APP_ID, 'sync_calendars', $this->boolParam('syncCalendars') ? '1' : '0');
        $this->config->setUserValue($userId, Application::APP_ID, 'interval_minutes', (string)$intervalMinutes);
        $this->config->setUserValue($userId, Application::APP_ID, 'enabled', '1');

        return new DataResponse([
            'ok' => true,
            'settings' => $this->settingsForResponse($userId),
            'adminSettings' => $this->adminSettingsForResponse(),
            'canManageAdminSettings' => $this->isAdmin($userId),
        ]);
    }

    /**
     * @NoAdminRequired
     */
    public function syncNow(): DataResponse {
        $userId = $this->getUserId();
        if ($userId === '') {
            return new DataResponse(['ok' => false, 'message' => 'Current Nextcloud user is not logged in.'], 401);
        }

        $result = $this->syncService->syncUser($userId);
        $ok = ($result['contacts']['ok'] ?? true) && ($result['calendars']['ok'] ?? true);

        return new DataResponse([
            'ok' => $ok,
            'message' => $ok ? 'Coremail sync completed.' : 'Coremail sync finished with errors.',
            'result' => $result,
            'settings' => $this->settingsForResponse($userId),
            'adminSettings' => $this->adminSettingsForResponse(),
            'canManageAdminSettings' => $this->isAdmin($userId),
        ], $ok ? 200 : 502);
    }

    private function settingsForResponse(string $userId): array {
        $password = $this->config->getUserValue($userId, Application::APP_ID, 'password', '');
        $lastRun = $this->config->getUserValue($userId, Application::APP_ID, 'last_run', '');
        $summary = $this->config->getUserValue($userId, Application::APP_ID, 'last_summary', '');
        $userDavBaseUrl = $this->config->getUserValue($userId, Application::APP_ID, 'dav_base_url', '');
        $effectiveDavBaseUrl = $userDavBaseUrl !== '' ? $userDavBaseUrl : $this->adminDavBaseUrl();

        return [
            'email' => $this->config->getUserValue($userId, Application::APP_ID, 'email', $this->userSession->getUser()?->getEMailAddress() ?? ''),
            'davBaseUrl' => $userDavBaseUrl,
            'effectiveDavBaseUrl' => $effectiveDavBaseUrl,
            'mode' => $this->securityMode(),
            'syncContacts' => $this->config->getUserValue($userId, Application::APP_ID, 'sync_contacts', '1') === '1',
            'syncCalendars' => $this->config->getUserValue($userId, Application::APP_ID, 'sync_calendars', '1') === '1',
            'intervalMinutes' => (int)$this->config->getUserValue($userId, Application::APP_ID, 'interval_minutes', '30'),
            'configured' => $password !== '',
            'passwordStored' => $password !== '',
            'lastRun' => $lastRun !== '' ? date('Y-m-d H:i:s', (int)$lastRun) : '',
            'lastSummary' => $summary,
        ];
    }

    private function adminSettingsForResponse(): array {
        return [
            'mode' => $this->securityMode(),
            'davBaseUrl' => $this->adminDavBaseUrl(),
        ];
    }

    private function saveAdminSettings(): void {
        $mode = $this->sanitizeMode((string)$this->incomingRequest->getParam('securityMode', $this->securityMode()));
        $davBaseUrl = trim((string)$this->incomingRequest->getParam('adminDavBaseUrl', $this->adminDavBaseUrl()));

        $this->config->setAppValue(Application::APP_ID, 'security_mode', $mode);
        $this->config->setAppValue(Application::APP_ID, 'dav_base_url', $davBaseUrl === '' ? '' : rtrim($davBaseUrl, '/'));
    }

    private function securityMode(): string {
        return $this->sanitizeMode($this->config->getAppValue(Application::APP_ID, 'security_mode', self::MODE_PRODUCTION));
    }

    private function sanitizeMode(string $mode): string {
        return $mode === self::MODE_DEMO ? self::MODE_DEMO : self::MODE_PRODUCTION;
    }

    private function adminDavBaseUrl(): string {
        return $this->config->getAppValue(Application::APP_ID, 'dav_base_url', '');
    }

    private function boolParam(string $name): bool {
        $value = $this->incomingRequest->getParam($name, false);
        return $value === true || $value === 'true' || $value === '1' || $value === 1;
    }

    private function getUserId(): string {
        return $this->userSession->getUser()?->getUID() ?? '';
    }

    private function isAdmin(string $userId): bool {
        return $userId !== '' && $this->groupManager->isAdmin($userId);
    }
}
