<?php

declare(strict_types=1);

namespace Sylius\Bundle\ResourceBundle\Controller;

use Symfony\Component\Security\Core\Exception\AccessDeniedException;

interface ActionAuthorizerInterface
{
    /**
     * @throws AccessDeniedException
     */
    public function isGrantedOr403(RequestConfiguration $configuration, string $permission): void;
}
