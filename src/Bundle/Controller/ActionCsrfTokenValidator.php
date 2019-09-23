<?php

declare(strict_types=1);

namespace Sylius\Bundle\ResourceBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class ActionCsrfTokenValidator implements ActionCsrfTokenValidatorInterface
{
    /** @var CsrfTokenManagerInterface */
    private $csrfTokenManager;

    public function __construct(CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->csrfTokenManager = $csrfTokenManager;
    }

    /**
     * @throws HttpException
     */
    public function isCsrfValidOr403(
        Request $request,
        RequestConfiguration $configuration,
        string $id
    ): void {
        if (!$configuration->isCsrfProtectionEnabled()) {
            return;
        }

        if (!$this->isCsrfTokenValid($id, $request->request->get('_csrf_token'))) {
            throw new AccessDeniedException('Invalid csrf token.');
        }
    }

    private function isCsrfTokenValid(string $id, ?string $token): bool
    {
        return $this->csrfTokenManager->isTokenValid(new CsrfToken($id, $token));
    }
}
