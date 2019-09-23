<?php

declare(strict_types=1);

namespace Sylius\Bundle\ResourceBundle\Controller;

use Sylius\Component\Resource\Model\ResourceInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

interface ActionSingleResourceProviderInterface
{
    /** @throws NotFoundHttpException */
    public function findOr404(RequestConfiguration $configuration): ResourceInterface;
}
