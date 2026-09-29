<?php
// src/EventSubscriber/CartSubscriber.php

namespace App\EventSubscriber;

use App\Cart\CartService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

/**
 * Exposes the number of articles in the cart to every template ("cart_count").
 */
class CartSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly CartService $cart,
    ) {
    }

    public function onKernelController(ControllerEvent $event): void
    {
        // Sub-requests render fragments of a page that already has the value.
        if (!$event->isMainRequest()) {
            return;
        }

        $this->twig->addGlobal('cart_count', $this->cart->countItems());
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }
}
