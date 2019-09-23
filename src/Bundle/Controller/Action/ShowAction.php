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
use Sylius\Bundle\ResourceBundle\Controller\ActionSingleResourceProviderInterface;
use Sylius\Bundle\ResourceBundle\Controller\EventDispatcherInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfigurationFactoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\ViewHandlerInterface;
use Sylius\Component\Resource\Metadata\MetadataInterface;
use Sylius\Component\Resource\ResourceActions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ShowAction
{
    /** @var MetadataInterface */
    private $metadata;

    /** @var RequestConfigurationFactoryInterface */
    private $requestConfigurationFactory;

    /** @var ViewHandlerInterface */
    private $viewHandler;

    /** @var ActionAuthorizerInterface */
    private $authorizer;

    /** @var ActionSingleResourceProviderInterface */
    private $singleResourceProvider;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    public function __construct(
        MetadataInterface $metadata,
        RequestConfigurationFactoryInterface $requestConfigurationFactory,
        ViewHandlerInterface $viewHandler,
        ActionAuthorizerInterface $authorizer,
        ActionSingleResourceProviderInterface $singleResourceProvider,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->metadata = $metadata;
        $this->requestConfigurationFactory = $requestConfigurationFactory;
        $this->viewHandler = $viewHandler;
        $this->singleResourceProvider = $singleResourceProvider;
        $this->authorizer = $authorizer;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function __invoke(Request $request): Response
    {
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);

        $this->authorizer->isGrantedOr403($configuration, ResourceActions::SHOW);
        $resource = $this->singleResourceProvider->findOr404($configuration);

        $this->eventDispatcher->dispatch(ResourceActions::SHOW, $configuration, $resource);

        $view = View::create($resource);

        if ($configuration->isHtmlRequest()) {
            $view
                ->setTemplate($configuration->getTemplate(ResourceActions::SHOW . '.html'))
                ->setTemplateVar($this->metadata->getName())
                ->setData([
                    'configuration' => $configuration,
                    'metadata' => $this->metadata,
                    'resource' => $resource,
                    $this->metadata->getName() => $resource,
                ])
            ;
        }

        return $this->viewHandler->handle($configuration, $view);
    }
}
