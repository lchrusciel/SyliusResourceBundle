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

use Doctrine\Common\Persistence\ObjectManager;
use FOS\RestBundle\View\View;
use Sylius\Bundle\ResourceBundle\Controller\ActionAuthorizerInterface;
use Sylius\Bundle\ResourceBundle\Controller\ActionCsrfTokenValidatorInterface;
use Sylius\Bundle\ResourceBundle\Controller\ActionSingleResourceProviderInterface;
use Sylius\Bundle\ResourceBundle\Controller\EventDispatcherInterface;
use Sylius\Bundle\ResourceBundle\Controller\FlashHelperInterface;
use Sylius\Bundle\ResourceBundle\Controller\RedirectHandlerInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfigurationFactoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\ResourceUpdateHandlerInterface;
use Sylius\Bundle\ResourceBundle\Controller\StateMachineInterface;
use Sylius\Bundle\ResourceBundle\Controller\ViewHandlerInterface;
use Sylius\Component\Resource\Exception\UpdateHandlingException;
use Sylius\Component\Resource\Metadata\MetadataInterface;
use Sylius\Component\Resource\ResourceActions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ApplyStateMachineTransitionAction
{
    /** @var MetadataInterface */
    private $metadata;

    /** @var RequestConfigurationFactoryInterface */
    private $requestConfigurationFactory;

    /** @var ViewHandlerInterface */
    private $viewHandler;

    /** @var ObjectManager */
    private $manager;

    /** @var ActionAuthorizerInterface */
    private $authorizer;

    /** @var ActionSingleResourceProviderInterface */
    private $singleResourceProvider;

    /** @var ActionCsrfTokenValidatorInterface */
    private $csrfTokenValidator;

    /** @var RedirectHandlerInterface */
    private $redirectHandler;

    /** @var FlashHelperInterface */
    private $flashHelper;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    /** @var StateMachineInterface */
    private $stateMachine;

    /** @var ResourceUpdateHandlerInterface */
    private $resourceUpdateHandler;

    public function __construct(
        MetadataInterface $metadata,
        RequestConfigurationFactoryInterface $requestConfigurationFactory,
        ViewHandlerInterface $viewHandler,
        ObjectManager $manager,
        ActionAuthorizerInterface $authorizer,
        ActionSingleResourceProviderInterface $singleResourceProvider,
        ActionCsrfTokenValidatorInterface $csrfTokenValidator,
        RedirectHandlerInterface $redirectHandler,
        FlashHelperInterface $flashHelper,
        EventDispatcherInterface $eventDispatcher,
        StateMachineInterface $stateMachine,
        ResourceUpdateHandlerInterface $resourceUpdateHandler
    ) {
        $this->metadata = $metadata;
        $this->requestConfigurationFactory = $requestConfigurationFactory;
        $this->viewHandler = $viewHandler;
        $this->manager = $manager;
        $this->authorizer = $authorizer;
        $this->singleResourceProvider = $singleResourceProvider;
        $this->csrfTokenValidator = $csrfTokenValidator;
        $this->redirectHandler = $redirectHandler;
        $this->flashHelper = $flashHelper;
        $this->eventDispatcher = $eventDispatcher;
        $this->stateMachine = $stateMachine;
        $this->resourceUpdateHandler = $resourceUpdateHandler;
    }

    public function __invoke(Request $request): Response
    {
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);

        $this->authorizer->isGrantedOr403($configuration, ResourceActions::UPDATE);
        $resource = $this->singleResourceProvider->findOr404($configuration);

        $this->csrfTokenValidator->isCsrfValidOr403($request, $configuration, $resource->getId());

        $event = $this->eventDispatcher->dispatchPreEvent(ResourceActions::UPDATE, $configuration, $resource);

        if ($event->isStopped() && !$configuration->isHtmlRequest()) {
            throw new HttpException($event->getErrorCode(), $event->getMessage());
        }
        if ($event->isStopped()) {
            $this->flashHelper->addFlashFromEvent($configuration, $event);

            if ($event->hasResponse()) {
                return $event->getResponse();
            }

            return $this->redirectHandler->redirectToResource($configuration, $resource);
        }

        if (!$this->stateMachine->can($configuration, $resource)) {
            throw new BadRequestHttpException();
        }

        try {
            $this->resourceUpdateHandler->handle($resource, $configuration, $this->manager);
        } catch (UpdateHandlingException $exception) {
            if (!$configuration->isHtmlRequest()) {
                return $this->viewHandler->handle(
                    $configuration,
                    View::create($resource, $exception->getApiResponseCode())
                );
            }

            $this->flashHelper->addErrorFlash($configuration, $exception->getFlash());

            return $this->redirectHandler->redirectToReferer($configuration);
        }

        $postEvent = $this->eventDispatcher->dispatchPostEvent(ResourceActions::UPDATE, $configuration, $resource);

        if (!$configuration->isHtmlRequest()) {
            $view = $configuration->getParameters()->get('return_content', true) ? View::create($resource, Response::HTTP_OK) : View::create(null, Response::HTTP_NO_CONTENT);

            return $this->viewHandler->handle($configuration, $view);
        }

        $this->flashHelper->addSuccessFlash($configuration, ResourceActions::UPDATE, $resource);

        if ($postEvent->hasResponse()) {
            return $postEvent->getResponse();
        }

        return $this->redirectHandler->redirectToResource($configuration, $resource);
    }
}
