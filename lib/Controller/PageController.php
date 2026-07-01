<?php

declare(strict_types=1);

namespace OCA\CoremailSync\Controller;

use OCA\CoremailSync\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IUserSession;

class PageController extends Controller {
    public function __construct(
        IRequest $request,
        private IUserSession $userSession,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse {
        $user = $this->userSession->getUser();

        return new TemplateResponse(Application::APP_ID, 'main', [
            'userId' => $user?->getUID() ?? '',
            'displayName' => $user?->getDisplayName() ?? '',
            'email' => $user?->getEMailAddress() ?? '',
        ]);
    }
}
