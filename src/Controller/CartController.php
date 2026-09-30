<?php

namespace App\Controller;

use App\Cart\CartService;
use App\Cart\Exception\OutOfStockException;
use App\Entity\Product;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/cart')]
class CartController extends AbstractController
{
    public function __construct(private readonly CartService $cart)
    {
    }

    #[Route('', name: 'app_cart')]
    public function index(): Response
    {
        $totals = $this->cart->getTotals();

        return $this->render('cart/index.html.twig', [
            'cart_items' => $this->cart->getLines(),
            'cart_subtotal' => $totals->subtotal,
            'shipping_cost' => $totals->shipping,
            'tax' => $totals->tax,
            'cart_total' => $totals->total,
        ]);
    }

    #[Route('/add/{id}', name: 'app_cart_add', methods: ['POST'])]
    public function add(Request $request, Product $product): Response
    {
        if (!$this->isCsrfTokenValid('cart', (string) $request->request->get('_token'))) {
            return $this->invalidTokenResponse($request);
        }

        $requested = max(1, $request->request->getInt('quantity', 1));
        $back = $this->redirect($request->headers->get('referer') ?? $this->generateUrl('app_cart'));

        try {
            $added = $this->cart->add($product, $requested, ...$this->options($request));
        } catch (OutOfStockException) {
            $this->addFlash('error', sprintf('« %s » n’est plus en stock.', $product->getName()));
            return $back;
        }

        if ($added < $requested) {
            $this->addFlash('warning', $added > 0
                ? sprintf('Stock limité : seulement %d article(s) ajouté(s).', $added)
                : 'Vous avez déjà tout le stock disponible dans votre panier.');
        } elseif (!$request->isXmlHttpRequest()) {
            $this->addFlash('success', 'Produit ajouté au panier.');
        }

        return $back;
    }

    #[Route('/update/{id}', name: 'app_cart_update', methods: ['POST'])]
    public function update(Request $request, Product $product): Response
    {
        if (!$this->isCsrfTokenValid('cart', (string) $request->request->get('_token'))) {
            return $this->invalidTokenResponse($request);
        }

        $requested = max(1, $request->request->getInt('quantity', 1));

        try {
            $quantity = $this->cart->update($product, $requested, ...$this->options($request));
        } catch (OutOfStockException) {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => false, 'error' => 'Out of stock.'], Response::HTTP_CONFLICT);
            }
            $this->addFlash('error', sprintf('« %s » n’est plus en stock : retirez-le du panier.', $product->getName()));
            return $this->redirectToRoute('app_cart');
        }

        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true, 'quantity' => $quantity]);
        }

        if ($quantity < $requested) {
            $this->addFlash('warning', sprintf('Stock limité : quantité ramenée à %d.', $quantity));
        }

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/remove/{id}', name: 'app_cart_remove', methods: ['POST'])]
    public function remove(Request $request, Product $product): Response
    {
        if (!$this->isCsrfTokenValid('cart', (string) $request->request->get('_token'))) {
            return $this->invalidTokenResponse($request);
        }

        $this->cart->remove($product, ...$this->options($request));

        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true]);
        }

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/clear', name: 'app_cart_clear', methods: ['POST'])]
    public function clear(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('cart', (string) $request->request->get('_token'))) {
            return $this->invalidTokenResponse($request);
        }

        $this->cart->clear();

        $this->addFlash('success', 'Panier vidé.');
        return $this->redirectToRoute('app_cart');
    }

    /**
     * Product options posted by the forms; empty strings mean "no option".
     *
     * @return array{color: ?string, storage: ?string}
     */
    private function options(Request $request): array
    {
        $option = fn (string $name): ?string => mb_substr(trim((string) $request->request->get($name, '')), 0, 50) ?: null;

        return ['color' => $option('color'), 'storage' => $option('storage')];
    }

    private function invalidTokenResponse(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => false, 'error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        $this->addFlash('error', 'Votre session a expiré, veuillez réessayer.');
        return $this->redirectToRoute('app_cart');
    }
}
