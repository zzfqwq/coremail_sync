<?php

declare(strict_types=1);

namespace OCA\CoremailSync\BackgroundJob;

use OCA\CoremailSync\AppInfo\Application;
use OCA\CoremailSync\Service\SyncService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use OCP\IUserManager;

class SyncJob extends TimedJob {
    public function __construct(
        ITimeFactory $time,
        private IConfig $config,
        private IUserManager $userManager,
        private SyncService $syncService,
    ) {
        parent::__construct($time);
        $this->setInterval(60);
    }

    protected function run($argument): void {
        $now = time();
        $userIds = $this->config->getUsersForUserValue(Application::APP_ID, 'enabled', '1');

        foreach ($userIds as $userId) {
            if (!$this->userManager->userExists($userId)) {
                continue;
            }
            if (!$this->syncService->shouldSyncUser($userId, $now)) {
                continue;
            }

            $this->syncService->syncUser($userId);
        }
    }
}
