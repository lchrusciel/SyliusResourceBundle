<?php

declare(strict_types=1);

namespace Sylius\Bundle\ResourceBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

interface ActionCsrfTokenValidatorInterface
{
    /** @throws AccessDeniedException */
    public function isCsrfValidOr403(Request $request, RequestConfiguration $configuration, string $id): void;
}
