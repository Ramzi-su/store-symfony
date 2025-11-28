<?php

namespace App\Controller;

use App\Entity\CartItem;
use App\Entity\Product;
use App\Repository\CartItemRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/cart')]
class CartController extends AbstractController
{
    private EntityManagerInterface $em;
    private CartItemRepository $cartItemRepo;
    private ProductRepository $productRepo;
    private SessionInterface $session;
    private CsrfTokenManagerInterface $csrf;

    public function __construct(
        EntityManagerInterface $em,
        CartItemRepository $cartItemRepo,
        ProductRepository $productRepo,
        RequestStack $requestStack,
        CsrfTokenManagerInterface $csrf
    ) {
        $this->em = $em;
        $this->cartItemRepo = $cartItemRepo;
        $this->productRepo = $productRepo;
        $this->session = $requestStack->getSession();
        $this->csrf = $csrf;
    }

    private function generateKey(Product $product, ?string $color, ?string $storage): string
    {
        return $product->getId() . '-' . ($color ?? '') . '-' . ($storage ?? '');
    }

    #[Route('', name: 'app_cart')]
    public function index(): Response
    {
        $cartItems = [];
        $subtotal = $shipping = $tax = $total = 0.0;

        if ($this->getUser()) {
            $cartItems = $this->cartItemRepo->findBy(['user' => $this->getUser()]);
            foreach ($cartItems as $item) {
                $subtotal += $item->getProduct()->getPrice() * $item->getQuantity();
            }
        } else {
            foreach ($this->session->get('cart', []) as $key => $item) {
                [$id] = explode('-', $key);
                $product = $this->productRepo->find($id);
                if ($product) {
                    $cartItems[] = [
                        'product' => $product,
                        'quantity' => $item['quantity'],
                        'color' => $item['color'] ?? null,
                        'storage' => $item['storage'] ?? null,
                    ];
                    $subtotal += $product->getPrice() * $item['quantity'];
                }
            }
        }

        if ($subtotal > 0) {
            $shipping = 10.0;
            $tax = $subtotal * 0.1;
        }

        $total = $subtotal + $shipping + $tax;

        return $this->render('cart/index.html.twig', [
            'cart_items' => $cartItems,
            'cart_subtotal' => $subtotal,
            'shipping_cost' => $shipping,
            'tax' => $tax,
            'cart_total' => $total
        ]);
    }

    #[Route('/add/{id}', name: 'app_cart_add', methods: ['POST','GET'])]
    public function add(Request $request, Product $product): Response
    {
        $quantity = max(1, min(99, (int)$request->request->get('quantity', 1)));
        $color = $request->request->get('color');
        $storage = $request->request->get('storage');

        if ($this->getUser()) {
            $item = $this->cartItemRepo->findOneBy([
                'user' => $this->getUser(),
                'product' => $product,
                'color' => $color,
                'storage' => $storage
            ]) ?? new CartItem();

            if ($item->getId()) {
                $item->setQuantity($item->getQuantity() + $quantity);
            } else {
                $item->setUser($this->getUser())
                     ->setProduct($product)
                     ->setColor($color)
                     ->setStorage($storage)
                     ->setQuantity($quantity);
                $this->em->persist($item);
            }
            $this->em->flush();
        } else {
            $key = $this->generateKey($product, $color, $storage);
            $cart = $this->session->get('cart', []);
            if (isset($cart[$key])) {
                $cart[$key]['quantity'] += $quantity;
            } else {
                $cart[$key] = ['quantity' => $quantity, 'color' => $color, 'storage' => $storage];
            }
            $this->session->set('cart', $cart);
        }

        $this->addFlash('success', 'Produit ajouté au panier.');
        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('app_cart'));
    }

    #[Route('/update/{id}', name: 'app_cart_update', methods: ['POST'])]
    public function update(Request $request, Product $product): Response
    {
        $quantity = max(1, min(99, (int)$request->request->get('quantity', 1)));
        $color = $request->request->get('color');
        $storage = $request->request->get('storage');

        if ($this->getUser()) {
            $item = $this->cartItemRepo->findOneBy([
                'user' => $this->getUser(),
                'product' => $product,
                'color' => $color,
                'storage' => $storage
            ]);
            if ($item) {
                $item->setQuantity($quantity);
                $this->em->flush();
            }
        } else {
            $key = $this->generateKey($product, $color, $storage);
            $cart = $this->session->get('cart', []);
            if (isset($cart[$key])) {
                $cart[$key]['quantity'] = $quantity;
                $this->session->set('cart', $cart);
            }
        }

        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true]);
        }

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/remove/{id}', name: 'app_cart_remove', methods: ['POST'])]
    public function remove(Request $request, Product $product): Response
    {
        $color = $request->request->get('color');
        $storage = $request->request->get('storage');

        if ($this->getUser()) {
            $item = $this->cartItemRepo->findOneBy([
                'user' => $this->getUser(),
                'product' => $product,
                'color' => $color,
                'storage' => $storage
            ]);
            if ($item) {
                $this->em->remove($item);
                $this->em->flush();
            }
        } else {
            $key = $this->generateKey($product, $color, $storage);
            $cart = $this->session->get('cart', []);
            unset($cart[$key]);
            $this->session->set('cart', $cart);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true]);
        }

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/clear', name: 'app_cart_clear', methods: ['POST'])]
    public function clear(): Response
    {
        if ($this->getUser()) {
            $items = $this->cartItemRepo->findBy(['user' => $this->getUser()]);
            foreach ($items as $item) {
                $this->em->remove($item);
            }
            $this->em->flush();
        } else {
            $this->session->remove('cart');
        }

        $this->addFlash('success', 'Panier vidé.');
        return $this->redirectToRoute('app_cart');
    }
}
