<?php

namespace App\Controller;

use App\Cart\CartService;
use App\Entity\Product;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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

        $this->cart->add($product, $request->request->getInt('quantity', 1), ...$this->options($request));

        $this->addFlash('success', 'Produit ajouté au panier.');
        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('app_cart'));
    }

    #[Route('/update/{id}', name: 'app_cart_update', methods: ['POST'])]
    public function update(Request $request, Product $product): Response
    {
        if (!$this->isCsrfTokenValid('cart', (string) $request->request->get('_token'))) {
            return $this->invalidTokenResponse($request);
        }

        $this->cart->update($product, $request->request->getInt('quantity', 1), ...$this->options($request));

        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true]);
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
