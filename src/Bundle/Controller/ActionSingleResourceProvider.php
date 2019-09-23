<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\Bundle\ResourceBundle\Controller;

use Sylius\Component\Resource\Metadata\MetadataInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ActionSingleResourceProvider implements ActionSingleResourceProviderInterface
{
    /** @var MetadataInterface */
    private $metadata;

    /** @var RepositoryInterface */
    private $repository;

    /** @var SingleResourceProviderInterface */
    private $singleResourceProvider;

    public function __construct(
        MetadataInterface $metadata,
        RepositoryInterface $repository,
        SingleResourceProviderInterface $singleResourceProvider
    ) {
        $this->metadata = $metadata;
        $this->repository = $repository;
        $this->singleResourceProvider = $singleResourceProvider;
    }

    public function findOr404(RequestConfiguration $configuration): ResourceInterface
    {
        if (null === $resource = $this->singleResourceProvider->get($configuration, $this->repository)) {
            throw new NotFoundHttpException(
                sprintf('The "%s" has not been found', $this->metadata->getHumanizedName())
            );
        }

        return $resource;


    }
}
