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

namespace Sylius\Bundle\ResourceBundle\Controller\Action;

use FOS\RestBundle\View\View;
use Sylius\Bundle\ResourceBundle\Controller\ActionAuthorizerInterface;
use Sylius\Bundle\ResourceBundle\Controller\EventDispatcherInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfigurationFactoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\ResourcesCollectionProviderInterface;
use Sylius\Bundle\ResourceBundle\Controller\ViewHandlerInterface;
use Sylius\Component\Resource\Metadata\MetadataInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Component\Resource\ResourceActions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class IndexAction
{
    /** @var MetadataInterface */
    private $metadata;

    /** @var RequestConfigurationFactoryInterface */
    private $requestConfigurationFactory;

    /** @var ViewHandlerInterface */
    private $viewHandler;

    /** @var RepositoryInterface */
    private $repository;

    /** @var ResourcesCollectionProviderInterface */
    private $resourcesCollectionProvider;

    /** @var ActionAuthorizerInterface */
    private $authorizer;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    public function __construct(
        MetadataInterface $metadata,
        RequestConfigurationFactoryInterface $requestConfigurationFactory,
        ViewHandlerInterface $viewHandler,
        RepositoryInterface $repository,
        ResourcesCollectionProviderInterface $resourcesFinder,
        ActionAuthorizerInterface $authorizer,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->metadata = $metadata;
        $this->requestConfigurationFactory = $requestConfigurationFactory;
        $this->viewHandler = $viewHandler;
        $this->repository = $repository;
        $this->resourcesCollectionProvider = $resourcesFinder;
        $this->authorizer = $authorizer;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function __invoke(Request $request): Response
    {
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);

        $this->authorizer->isGrantedOr403($configuration, ResourceActions::INDEX);
        $resources = $this->resourcesCollectionProvider->get($configuration, $this->repository);

        $this->eventDispatcher->dispatchMultiple(ResourceActions::INDEX, $configuration, $resources);

        $view = View::create($resources);

        if ($configuration->isHtmlRequest()) {
            $view
                ->setTemplate($configuration->getTemplate(ResourceActions::INDEX . '.html'))
                ->setTemplateVar($this->metadata->getPluralName())
                ->setData([
                    'configuration' => $configuration,
                    'metadata' => $this->metadata,
                    'resources' => $resources,
                    $this->metadata->getPluralName() => $resources,
                ])
            ;
        }

        return $this->viewHandler->handle($configuration, $view);
    }
}
