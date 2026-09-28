<?php

namespace App\Controller;

use App\Entity\Orders;
use App\Entity\OrderItems;
use App\Repository\CartItemRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Stripe;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/checkout')]
class CheckoutController extends AbstractController
{
    private EntityManagerInterface $em;
    private CartItemRepository $cartRepo;
    private ProductRepository $productRepo;
    private SessionInterface $session;
    private ?string $stripeSecretKey;
    private ?string $stripeWebhookSecret;

    public function __construct(
        EntityManagerInterface $em,
        CartItemRepository $cartRepo,
        ProductRepository $productRepo,
        RequestStack $requestStack,
        ?string $stripeSecretKey,
        ?string $stripeWebhookSecret
    ) {
        $this->em = $em;
        $this->cartRepo = $cartRepo;
        $this->productRepo = $productRepo;
        $this->session = $requestStack->getSession();
        $this->stripeSecretKey = $stripeSecretKey;
        $this->stripeWebhookSecret = $stripeWebhookSecret;
    }

    #[Route('', name: 'app_checkout')]
    public function index(): Response
    {
        if ($this->isCartEmpty()) {
            $this->addFlash('error', 'Votre panier est vide.');
            return $this->redirectToRoute('app_cart');
        }

        [$items, $subtotal, $shipping, $tax, $total] = $this->getCartDetails();

        return $this->render('checkout/checkout.html.twig', [
            'cart_items' => $items,
            'cart_subtotal' => $subtotal,
            'shipping_cost' => $shipping,
            'tax' => $tax,
            'cart_total' => $total,
        ]);
    }

    #[Route('/create-session', name: 'app_checkout_create_session', methods: ['POST'])]
    public function createSession(Request $request, LoggerInterface $logger, ValidatorInterface $validator): Response
    {
        if (!$this->isCsrfTokenValid('checkout', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, veuillez réessayer.');
            return $this->redirectToRoute('app_checkout');
        }

        if ($this->isCartEmpty()) {
            $this->addFlash('error', 'Votre panier est vide.');
            return $this->redirectToRoute('app_cart');
        }

        // Without Stripe there is no way to take a payment: refuse instead of faking a success.
        if (!$this->stripeSecretKey) {
            $logger->error('Checkout attempted but STRIPE_SECRET_KEY is not configured.');
            $this->addFlash('error', 'Le paiement est momentanément indisponible.');
            return $this->redirectToRoute('app_checkout');
        }

        [$items, $subtotal, $shipping, $tax, $total] = $this->getCartDetails();

        $order = new Orders();
        $order->setUser($this->getUser());
        $order->setStatus('pending');
        $order->setTotal($total);
        $order->setSubtotal($subtotal);
        $order->setTax($tax);
        $order->setShippingCost($shipping);
        $order->setCreatedAt(new \DateTimeImmutable());

        // Form field name => entity setter. Values are trimmed strings (null when empty),
        // then checked by the constraints of the "checkout" validation group.
        $fields = [
            'firstName' => 'setFirstName',
            'lastName' => 'setLastName',
            'email' => 'setEmail',
            'phone' => 'setPhoneNumber',
            'streetAddress' => 'setAddress',
            'city' => 'setCity',
            'state' => 'setState',
            'postcode' => 'setPostcode',
            'country' => 'setCountry',
        ];
        foreach ($fields as $field => $setter) {
            $value = trim((string) $request->request->get($field, ''));
            $order->$setter($value === '' ? null : $value);
        }

        $violations = $validator->validate($order, null, ['checkout']);
        if (count($violations) > 0) {
            foreach ($violations as $violation) {
                $this->addFlash('error', $violation->getPropertyPath() . ' : ' . $violation->getMessage());
            }
            return $this->redirectToRoute('app_checkout');
        }

        $this->em->persist($order);

        foreach ($items as $item) {
            $orderItem = new OrderItems();
            $orderItem->setOrder($order);
            $orderItem->setProduct($item['product']);
            $orderItem->setQuantity($item['quantity']);
            $orderItem->setPrice($item['product']->getPrice());
            $orderItem->setColor($item['color'] ?? null);
            $orderItem->setStorage($item['storage'] ?? null);
            $order->addItem($orderItem);
        }

        $this->em->flush();
        $this->session->set('order_id', $order->getId());

        try {
            Stripe::setApiKey($this->stripeSecretKey);
            $lineItems = [];

            foreach ($items as $item) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'usd',
                        'unit_amount' => $this->toCents($item['product']->getPrice()),
                        'product_data' => [
                            'name' => $item['product']->getName(),
                            // Stripe needs absolute, publicly reachable URLs.
                            'images' => $item['product']->getImage()
                                ? [$request->getSchemeAndHttpHost() . '/' . ltrim($item['product']->getImage(), '/')]
                                : [],
                        ],
                    ],
                    'quantity' => $item['quantity'],
                ];
            }

            if ($shipping > 0) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'usd',
                        'unit_amount' => $this->toCents($shipping),
                        'product_data' => ['name' => 'Shipping'],
                    ],
                    'quantity' => 1,
                ];
            }

            if ($tax > 0) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'usd',
                        'unit_amount' => $this->toCents($tax),
                        'product_data' => ['name' => 'Tax'],
                    ],
                    'quantity' => 1,
                ];
            }

            $session = StripeSession::create([
                'payment_method_types' => ['card'],
                'line_items' => $lineItems,
                'mode' => 'payment',
                'success_url' => $this->generateUrl('app_checkout_success', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'cancel_url' => $this->generateUrl('app_checkout_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'customer_email' => $order->getEmail(),
                'metadata' => ['order_id' => $order->getId()],
            ]);

            // Stored on the order (reconciliation, webhook check) and in the session
            // (so the success page knows which Stripe session to verify).
            $order->setStripeSessionId($session->id);
            $this->em->flush();
            $this->session->set('stripe_session_id', $session->id);

            return $this->redirect($session->url);
        } catch (ApiErrorException $e) {
            // Stripe error details stay in the logs, never in the user's browser.
            $logger->error('Stripe checkout session creation failed.', ['exception' => $e]);
            $this->addFlash('error', 'Le paiement n’a pas pu être initialisé. Veuillez réessayer.');
            return $this->redirectToRoute('app_checkout');
        }
    }

    #[Route('/success', name: 'app_checkout_success')]
    public function success(LoggerInterface $logger): Response
    {
        $orderId = $this->session->get('order_id');
        $stripeSessionId = $this->session->get('stripe_session_id');
        if (!$orderId || !$stripeSessionId || !$this->stripeSecretKey) {
            return $this->redirectToRoute('app_home');
        }

        $order = $this->em->getRepository(Orders::class)->find($orderId);
        if (!$order) {
            return $this->redirectToRoute('app_home');
        }

        // Anyone can open this URL: the redirect itself proves nothing.
        // Ask Stripe whether this checkout session was really paid for this order.
        try {
            Stripe::setApiKey($this->stripeSecretKey);
            $stripeSession = StripeSession::retrieve($stripeSessionId);
        } catch (ApiErrorException $e) {
            $logger->error('Could not retrieve Stripe checkout session.', ['exception' => $e]);
            $this->addFlash('error', 'Impossible de vérifier le paiement pour le moment.');
            return $this->redirectToRoute('app_checkout');
        }

        if (!$this->isPaidSessionForOrder($stripeSession, $order)) {
            $this->addFlash('error', 'Le paiement n’a pas été confirmé.');
            return $this->redirectToRoute('app_checkout');
        }

        $this->markOrderPaid($order);

        if ($this->getUser()) {
            foreach ($this->cartRepo->findBy(['user' => $this->getUser()]) as $item) {
                $this->em->remove($item);
            }
            $this->em->flush();
        } else {
            $this->session->remove('cart');
        }

        $this->session->remove('order_id');
        $this->session->remove('stripe_session_id');

        return $this->render('checkout/success.html.twig', ['order' => $order]);
    }

    /**
     * Stripe calls this endpoint server-to-server once a payment is completed.
     * It is the reliable source of truth: it still works if the customer closes
     * the tab before reaching the success page.
     */
    #[Route('/webhook', name: 'app_checkout_webhook', methods: ['POST'])]
    public function webhook(Request $request, LoggerInterface $logger): Response
    {
        if (!$this->stripeWebhookSecret) {
            $logger->error('Stripe webhook received but STRIPE_WEBHOOK_SECRET is not configured.');
            return new Response('', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        // The signature proves the request comes from Stripe and was not modified.
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->headers->get('Stripe-Signature'),
                $this->stripeWebhookSecret
            );
        } catch (\UnexpectedValueException | SignatureVerificationException) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        if ($event->type === 'checkout.session.completed') {
            $stripeSession = $event->data->object;
            $order = $this->em->getRepository(Orders::class)->find((int) ($stripeSession->metadata['order_id'] ?? 0));

            if ($order && $this->isPaidSessionForOrder($stripeSession, $order)) {
                $this->markOrderPaid($order);
            }
        }

        return new Response('', Response::HTTP_OK);
    }

    #[Route('/cancel', name: 'app_checkout_cancel')]
    public function cancel(): Response
    {
        $orderId = $this->session->get('order_id');
        if ($orderId) {
            $order = $this->em->getRepository(Orders::class)->find($orderId);
            if ($order) {
                $order->setStatus('cancelled');
                $this->em->flush();
            }
            $this->session->remove('order_id');
        }

        $this->addFlash('error', 'Paiement annulé.');
        return $this->redirectToRoute('app_checkout');
    }

    private function isPaidSessionForOrder(StripeSession $stripeSession, Orders $order): bool
    {
        return $stripeSession->payment_status === 'paid'
            && $stripeSession->id === $order->getStripeSessionId()
            && (string) ($stripeSession->metadata['order_id'] ?? '') === (string) $order->getId();
    }

    // Idempotent: the webhook and the success page may both report the same payment.
    private function markOrderPaid(Orders $order): void
    {
        if ($order->getStatus() !== 'paid') {
            $order->setStatus('paid');
            $this->em->flush();
        }
    }

    // round() first: 19.99 * 100 is 1998.9999... in floating point, and (int) alone would give 1998.
    private function toCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function isCartEmpty(): bool
    {
        if ($this->getUser()) {
            return count($this->cartRepo->findBy(['user' => $this->getUser()])) === 0;
        }
        return count($this->session->get('cart', [])) === 0;
    }

    private function getCartDetails(): array
    {
        $items = [];
        $subtotal = $shipping = $tax = $total = 0.0;

        if ($this->getUser()) {
            $cartItems = $this->cartRepo->findBy(['user' => $this->getUser()]);
            foreach ($cartItems as $item) {
                $subtotal += $item->getProduct()->getPrice() * $item->getQuantity();
                $items[] = [
                    'product' => $item->getProduct(),
                    'quantity' => $item->getQuantity(),
                    'color' => $item->getColor(),
                    'storage' => $item->getStorage(),
                ];
            }
        } else {
            foreach ($this->session->get('cart', []) as $key => $entry) {
                // Guest cart keys are "productId-color-storage" (see CartController::generateKey()).
                [$id] = explode('-', (string) $key);
                $product = $this->productRepo->find((int) $id);
                if ($product) {
                    $subtotal += $product->getPrice() * $entry['quantity'];
                    $items[] = [
                        'product' => $product,
                        'quantity' => $entry['quantity'],
                        'color' => $entry['color'] ?? null,
                        'storage' => $entry['storage'] ?? null,
                    ];
                }
            }
        }

        if ($subtotal > 0) {
            $shipping = 10.0;
            $tax = $subtotal * 0.1;
        }

        $total = $subtotal + $shipping + $tax;
        return [$items, $subtotal, $shipping, $tax, $total];
    }
}
