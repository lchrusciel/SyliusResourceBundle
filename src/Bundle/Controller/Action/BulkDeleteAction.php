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
use Sylius\Bundle\ResourceBundle\Controller\ActionCsrfTokenValidatorInterface;
use Sylius\Bundle\ResourceBundle\Controller\EventDispatcherInterface;
use Sylius\Bundle\ResourceBundle\Controller\FlashHelperInterface;
use Sylius\Bundle\ResourceBundle\Controller\RedirectHandlerInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfigurationFactoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\ResourceDeleteHandlerInterface;
use Sylius\Bundle\ResourceBundle\Controller\ResourcesCollectionProviderInterface;
use Sylius\Bundle\ResourceBundle\Controller\ViewHandlerInterface;
use Sylius\Component\Resource\Exception\DeleteHandlingException;
use Sylius\Component\Resource\Metadata\MetadataInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Component\Resource\ResourceActions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class BulkDeleteAction
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

    /** @var ActionCsrfTokenValidatorInterface */
    private $csrfTokenValidator;

    /** @var RedirectHandlerInterface */
    private $redirectHandler;

    /** @var FlashHelperInterface */
    private $flashHelper;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    /** @var ResourceDeleteHandlerInterface */
    private $resourceDeleteHandler;

    public function __construct(
        MetadataInterface $metadata,
        RequestConfigurationFactoryInterface $requestConfigurationFactory,
        ViewHandlerInterface $viewHandler,
        RepositoryInterface $repository,
        ResourcesCollectionProviderInterface $resourcesFinder,
        ActionAuthorizerInterface $authorizer,
        ActionCsrfTokenValidatorInterface $csrfTokenValidator,
        RedirectHandlerInterface $redirectHandler,
        FlashHelperInterface $flashHelper,
        EventDispatcherInterface $eventDispatcher,
        ResourceDeleteHandlerInterface $resourceDeleteHandler
    ) {
        $this->metadata = $metadata;
        $this->requestConfigurationFactory = $requestConfigurationFactory;
        $this->viewHandler = $viewHandler;
        $this->repository = $repository;
        $this->resourcesCollectionProvider = $resourcesFinder;
        $this->authorizer = $authorizer;
        $this->csrfTokenValidator = $csrfTokenValidator;
        $this->redirectHandler = $redirectHandler;
        $this->flashHelper = $flashHelper;
        $this->eventDispatcher = $eventDispatcher;
        $this->resourceDeleteHandler = $resourceDeleteHandler;
    }

    public function __invoke(Request $request): Response
    {
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);

        $this->authorizer->isGrantedOr403($configuration, ResourceActions::BULK_DELETE);
        $resources = $this->resourcesCollectionProvider->get($configuration, $this->repository);

        $this->csrfTokenValidator->isCsrfValidOr403($request, $configuration, ResourceActions::BULK_DELETE);

        $this->eventDispatcher->dispatchMultiple(ResourceActions::BULK_DELETE, $configuration, $resources);

        foreach ($resources as $resource) {
            $event = $this->eventDispatcher->dispatchPreEvent(ResourceActions::DELETE, $configuration, $resource);

            if ($event->isStopped() && !$configuration->isHtmlRequest()) {
                throw new HttpException($event->getErrorCode(), $event->getMessage());
            }
            if ($event->isStopped()) {
                $this->flashHelper->addFlashFromEvent($configuration, $event);

                if ($event->hasResponse()) {
                    return $event->getResponse();
                }

                return $this->redirectHandler->redirectToIndex($configuration, $resource);
            }

            try {
                $this->resourceDeleteHandler->handle($resource, $this->repository);
            } catch (DeleteHandlingException $exception) {
                if (!$configuration->isHtmlRequest()) {
                    return $this->viewHandler->handle(
                        $configuration,
                        View::create(null, $exception->getApiResponseCode())
                    );
                }

                $this->flashHelper->addErrorFlash($configuration, $exception->getFlash());

                return $this->redirectHandler->redirectToReferer($configuration);
            }

            $postEvent = $this->eventDispatcher->dispatchPostEvent(ResourceActions::DELETE, $configuration, $resource);
        }

        if (!$configuration->isHtmlRequest()) {
            return $this->viewHandler->handle($configuration, View::create(null, Response::HTTP_NO_CONTENT));
        }

        $this->flashHelper->addSuccessFlash($configuration, ResourceActions::BULK_DELETE);

        if (isset($postEvent) && $postEvent->hasResponse()) {
            return $postEvent->getResponse();
        }

        return $this->redirectHandler->redirectToIndex($configuration);
    }
}
