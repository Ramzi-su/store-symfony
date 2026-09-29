<?php

namespace App\Controller;

use App\Entity\Orders;
use App\Repository\OrdersRepository;
use App\Security\Voter\OrderVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/account')]
class OrderController extends AbstractController
{
    #[Route('/orders', name: 'app_orders')]
    #[IsGranted('ROLE_USER')]
    public function orders(OrdersRepository $ordersRepository): Response
    {
        $user = $this->getUser();
        $orders = $ordersRepository->findBy(['user' => $user], ['created_at' => 'DESC']);

        return $this->render('orders/index.html.twig', [
            'orders' => $orders,
        ]);
    }

    // The voter checks ownership: changing the id in the URL gives a 403, not someone else's order.
    #[Route('/orders/{id}', name: 'app_account_order_show', requirements: ['id' => '\d+'])]
    #[IsGranted(OrderVoter::VIEW, subject: 'order')]
    public function show(Orders $order): Response
    {
        return $this->render('orders/show.html.twig', [
            'order' => $order,
        ]);
    }
}
