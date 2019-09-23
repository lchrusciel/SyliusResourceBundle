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
use Sylius\Bundle\ResourceBundle\Controller\FlashHelperInterface;
use Sylius\Bundle\ResourceBundle\Controller\NewResourceFactoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\RedirectHandlerInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfigurationFactoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\ResourceFormFactoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\StateMachineInterface;
use Sylius\Bundle\ResourceBundle\Controller\ViewHandlerInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Metadata\MetadataInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Component\Resource\ResourceActions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CreateAction
{
    /** @var MetadataInterface */
    private $metadata;

    /** @var RequestConfigurationFactoryInterface */
    private $requestConfigurationFactory;

    /** @var ViewHandlerInterface */
    private $viewHandler;

    /** @var RepositoryInterface */
    private $repository;

    /** @var FactoryInterface */
    private $factory;

    /** @var NewResourceFactoryInterface */
    private $newResourceFactory;

    /** @var ResourceFormFactoryInterface */
    private $resourceFormFactory;

    /** @var ActionAuthorizerInterface */
    private $authorizer;

    /** @var RedirectHandlerInterface */
    private $redirectHandler;

    /** @var FlashHelperInterface */
    private $flashHelper;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    /** @var StateMachineInterface */
    private $stateMachine;

    public function __construct(
        MetadataInterface $metadata,
        RequestConfigurationFactoryInterface $requestConfigurationFactory,
        ViewHandlerInterface $viewHandler,
        RepositoryInterface $repository,
        FactoryInterface $factory,
        NewResourceFactoryInterface $newResourceFactory,
        ResourceFormFactoryInterface $resourceFormFactory,
        ActionAuthorizerInterface $authorizer,
        RedirectHandlerInterface $redirectHandler,
        FlashHelperInterface $flashHelper,
        EventDispatcherInterface $eventDispatcher,
        StateMachineInterface $stateMachine
    ) {
        $this->metadata = $metadata;
        $this->requestConfigurationFactory = $requestConfigurationFactory;
        $this->viewHandler = $viewHandler;
        $this->repository = $repository;
        $this->factory = $factory;
        $this->newResourceFactory = $newResourceFactory;
        $this->resourceFormFactory = $resourceFormFactory;
        $this->authorizer = $authorizer;
        $this->redirectHandler = $redirectHandler;
        $this->flashHelper = $flashHelper;
        $this->eventDispatcher = $eventDispatcher;
        $this->stateMachine = $stateMachine;
    }

    public function __invoke(Request $request): Response
    {
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);

        $this->authorizer->isGrantedOr403($configuration, ResourceActions::CREATE);
        $newResource = $this->newResourceFactory->create($configuration, $this->factory);

        $form = $this->resourceFormFactory->create($configuration, $newResource);

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $newResource = $form->getData();

            $event = $this->eventDispatcher->dispatchPreEvent(ResourceActions::CREATE, $configuration, $newResource);

            if ($event->isStopped() && !$configuration->isHtmlRequest()) {
                throw new HttpException($event->getErrorCode(), $event->getMessage());
            }
            if ($event->isStopped()) {
                $this->flashHelper->addFlashFromEvent($configuration, $event);

                if ($event->hasResponse()) {
                    return $event->getResponse();
                }

                return $this->redirectHandler->redirectToIndex($configuration, $newResource);
            }

            if ($configuration->hasStateMachine()) {
                $this->stateMachine->apply($configuration, $newResource);
            }

            $this->repository->add($newResource);
            $postEvent = $this->eventDispatcher->dispatchPostEvent(ResourceActions::CREATE, $configuration, $newResource);

            if (!$configuration->isHtmlRequest()) {
                return $this->viewHandler->handle($configuration, View::create($newResource, Response::HTTP_CREATED));
            }

            $this->flashHelper->addSuccessFlash($configuration, ResourceActions::CREATE, $newResource);

            if ($postEvent->hasResponse()) {
                return $postEvent->getResponse();
            }

            return $this->redirectHandler->redirectToResource($configuration, $newResource);
        }

        if (!$configuration->isHtmlRequest()) {
            return $this->viewHandler->handle($configuration, View::create($form, Response::HTTP_BAD_REQUEST));
        }

        $initializeEvent = $this->eventDispatcher->dispatchInitializeEvent(ResourceActions::CREATE, $configuration, $newResource);
        if ($initializeEvent->hasResponse()) {
            return $initializeEvent->getResponse();
        }

        $view = View::create()
            ->setData([
                'configuration' => $configuration,
                'metadata' => $this->metadata,
                'resource' => $newResource,
                $this->metadata->getName() => $newResource,
                'form' => $form->createView(),
            ])
            ->setTemplate($configuration->getTemplate(ResourceActions::CREATE . '.html'))
        ;

        return $this->viewHandler->handle($configuration, $view);
    }
}
