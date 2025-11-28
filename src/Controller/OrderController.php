<?php

namespace App\Controller;

use App\Repository\OrdersRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/account')]
class OrderController extends AbstractController
{
    #[Route('/orders', name: 'app_orders')]
    #[IsGranted('ROLE_USER')]
    public function orders(OrdersRepository $ordersRepository): Response
    {
        $user = $this->getUser();
        $orders = $ordersRepository->findBy(['user' => $user]);

        return $this->render('orders/index.html.twig', [
            'orders' => $orders,
        ]);
    }
}
