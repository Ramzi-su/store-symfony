<?php

namespace App\Controller;

use App\Entity\Orders;
use App\Enum\OrderStatus;
use App\Form\OrdersType;
use App\Order\StockReservation;
use App\Repository\OrdersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/orders')]
#[IsGranted('ROLE_ADMIN')]
final class OrdersController extends AbstractController
{
    #[Route(name: 'app_orders_index', methods: ['GET'])]
    public function index(OrdersRepository $ordersRepository): Response
    {
        return $this->render('orders/index.html.twig', [
            'orders' => $ordersRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_orders_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $order = new Orders();
        $form = $this->createForm(OrdersType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($order);
            $entityManager->flush();

            return $this->redirectToRoute('app_orders_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('orders/new.html.twig', [
            'order' => $order,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_orders_show', methods: ['GET'])]
    public function show(Orders $order): Response
    {
        return $this->render('orders/show.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_orders_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Orders $order, EntityManagerInterface $entityManager, StockReservation $stockReservation): Response
    {
        // handleRequest() writes the submitted status into the entity: remember the old one.
        $previousStatus = $order->getStatus();
        $form = $this->createForm(OrdersType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newStatus = $order->getStatus();

            if ($previousStatus === OrderStatus::Cancelled && $newStatus !== OrderStatus::Cancelled) {
                // Its stock was released when it was cancelled: re-reserving it could fail.
                $form->get('status')->addError(new FormError('Une commande annulée ne peut pas être réactivée : créez-en une nouvelle.'));
            } elseif ($newStatus === OrderStatus::Cancelled && $previousStatus !== OrderStatus::Cancelled && $previousStatus !== null) {
                // Save the other edited fields, then let StockReservation perform the
                // guarded status change so the stock is released exactly once.
                $order->setStatus($previousStatus);
                $entityManager->flush();
                $this->cancel($order, $previousStatus, $stockReservation);

                return $this->redirectToRoute('app_orders_index', [], Response::HTTP_SEE_OTHER);
            } else {
                $entityManager->flush();

                return $this->redirectToRoute('app_orders_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('orders/edit.html.twig', [
            'order' => $order,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_orders_delete', methods: ['POST'])]
    public function delete(Request $request, Orders $order, EntityManagerInterface $entityManager, StockReservation $stockReservation): Response
    {
        if ($this->isCsrfTokenValid('delete'.$order->getId(), $request->getPayload()->getString('_token'))) {
            // Deleting an order that still holds stock gives that stock back first.
            $status = $order->getStatus();
            if ($status === OrderStatus::Pending || $status === OrderStatus::Paid) {
                $stockReservation->cancelFrom($order, $status);
            }
            $entityManager->remove($order);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_orders_index', [], Response::HTTP_SEE_OTHER);
    }

    private function cancel(Orders $order, OrderStatus $from, StockReservation $stockReservation): void
    {
        // Pending and paid orders still hold their stock; shipped goods have left the warehouse.
        $restock = $from !== OrderStatus::Shipped;

        if (!$stockReservation->cancelFrom($order, $from, $restock)) {
            $this->addFlash('warning', sprintf('La commande #%d a changé de statut entre-temps : rien n’a été modifié.', $order->getId()));
        } elseif ($restock) {
            $this->addFlash('success', sprintf('Commande #%d annulée, articles remis en stock.', $order->getId()));
        } else {
            $this->addFlash('warning', sprintf('Commande #%d annulée. Elle était expédiée : le stock n’a pas été modifié, traitez le retour séparément.', $order->getId()));
        }
    }
}
