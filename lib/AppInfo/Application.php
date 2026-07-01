<?php

declare(strict_types=1);

namespace OCA\CoremailSync\AppInfo;

use OCA\CoremailSync\BackgroundJob\SyncJob;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\BackgroundJob\IJobList;
use OCP\INavigationManager;

class Application extends App implements IBootstrap {
    public const APP_ID = 'coremail_sync';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
    }

    public function boot(IBootContext $context): void {
        $server = $context->getServerContainer();
        $jobList = $server->get(IJobList::class);
        if (!$jobList->has(SyncJob::class, null)) {
            $jobList->add(SyncJob::class);
        }

        $navigationManager = $server->get(INavigationManager::class);
        $navigationManager->add(function () use ($server): array {
            $urlGenerator = $server->getURLGenerator();
            $l10n = $server->getL10N(self::APP_ID);

            return [
                'id' => self::APP_ID,
                'order' => 39,
                'href' => $urlGenerator->linkToRoute(self::APP_ID . '.page.index'),
                'icon' => $urlGenerator->imagePath(self::APP_ID, 'app.svg'),
                'name' => $l10n->t('Coremail Sync'),
            ];
        });
    }
}
