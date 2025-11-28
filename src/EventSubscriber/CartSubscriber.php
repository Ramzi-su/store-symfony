<?php
// src/EventSubscriber/CartSubscriber.php

namespace App\EventSubscriber;

use App\Repository\CartItemRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class CartSubscriber implements EventSubscriberInterface
{
    private $twig;
    private $cartItemRepository;
    private $requestStack;
    private $security;

    public function __construct(
        Environment $twig,
        CartItemRepository $cartItemRepository,
        RequestStack $requestStack,
        Security $security
    ) {
        $this->twig = $twig;
        $this->cartItemRepository = $cartItemRepository;
        $this->requestStack = $requestStack;
        $this->security = $security;
    }

    public function onKernelController(ControllerEvent $event): void
    {
        // Ne pas exécuter pour les sous-requêtes
        if (!$event->isMainRequest()) {
            return;
        }

        $cartCount = 0;

        // Si l'utilisateur est connecté, obtenir le nombre d'articles du panier depuis la base de données
        $user = $this->security->getUser();
        
        if ($user) {
            $cartItems = $this->cartItemRepository->findBy(['user' => $user]);
            foreach ($cartItems as $item) {
                $cartCount += $item->getQuantity();
            }
        } 
        // Sinon, obtenir le nombre d'articles du panier depuis la session
        else {
            $session = $this->requestStack->getSession();
            $cart = $session->get('cart', []);
            
            foreach ($cart as $item) {
                $cartCount += $item['quantity'] ?? 1;
            }
        }

        // Ajouter la variable globale à Twig
        $this->twig->addGlobal('cart_count', $cartCount);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }
}