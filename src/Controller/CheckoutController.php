<?php

namespace App\Controller;

use App\Cart\CartService;
use App\Entity\Orders;
use App\Entity\OrderItems;
use App\Enum\OrderStatus;
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
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/checkout')]
class CheckoutController extends AbstractController
{
    private EntityManagerInterface $em;
    private CartService $cart;
    private SessionInterface $session;
    private ?string $stripeSecretKey;
    private ?string $stripeWebhookSecret;

    public function __construct(
        EntityManagerInterface $em,
        CartService $cart,
        RequestStack $requestStack,
        ?string $stripeSecretKey,
        ?string $stripeWebhookSecret
    ) {
        $this->em = $em;
        $this->cart = $cart;
        $this->session = $requestStack->getSession();
        $this->stripeSecretKey = $stripeSecretKey;
        $this->stripeWebhookSecret = $stripeWebhookSecret;
    }

    #[Route('', name: 'app_checkout')]
    public function index(): Response
    {
        if ($this->cart->isEmpty()) {
            $this->addFlash('error', 'Votre panier est vide.');
            return $this->redirectToRoute('app_cart');
        }

        if ($response = $this->redirectIfStockIsShort()) {
            return $response;
        }

        $totals = $this->cart->getTotals();

        return $this->render('checkout/checkout.html.twig', [
            'cart_items' => $this->cart->getLines(),
            'cart_subtotal' => $totals->subtotal,
            'shipping_cost' => $totals->shipping,
            'tax' => $totals->tax,
            'cart_total' => $totals->total,
        ]);
    }

    #[Route('/create-session', name: 'app_checkout_create_session', methods: ['POST'])]
    public function createSession(Request $request, LoggerInterface $logger, ValidatorInterface $validator): Response
    {
        if (!$this->isCsrfTokenValid('checkout', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, veuillez réessayer.');
            return $this->redirectToRoute('app_checkout');
        }

        if ($this->cart->isEmpty()) {
            $this->addFlash('error', 'Votre panier est vide.');
            return $this->redirectToRoute('app_cart');
        }

        if ($response = $this->redirectIfStockIsShort()) {
            return $response;
        }

        // Without Stripe there is no way to take a payment: refuse instead of faking a success.
        if (!$this->stripeSecretKey) {
            $logger->error('Checkout attempted but STRIPE_SECRET_KEY is not configured.');
            $this->addFlash('error', 'Le paiement est momentanément indisponible.');
            return $this->redirectToRoute('app_checkout');
        }

        $lines = $this->cart->getLines();
        $totals = $this->cart->getTotals();

        $order = new Orders();
        $order->setUser($this->getUser());
        $order->setStatus(OrderStatus::Pending);
        $order->setTotal($totals->total);
        $order->setSubtotal($totals->subtotal);
        $order->setTax($totals->tax);
        $order->setShippingCost($totals->shipping);
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

        foreach ($lines as $line) {
            $orderItem = new OrderItems();
            $orderItem->setOrder($order);
            $orderItem->setProduct($line->product);
            $orderItem->setQuantity($line->quantity);
            $orderItem->setPrice($line->product->getPrice());
            $orderItem->setColor($line->color);
            $orderItem->setStorage($line->storage);
            $order->addItem($orderItem);
        }

        $this->em->flush();
        $this->session->set('order_id', $order->getId());

        try {
            Stripe::setApiKey($this->stripeSecretKey);
            $lineItems = [];

            foreach ($lines as $line) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'usd',
                        'unit_amount' => $line->product->getPrice(),
                        'product_data' => [
                            'name' => $line->product->getName(),
                            // Stripe needs absolute, publicly reachable URLs.
                            'images' => $line->product->getImage()
                                ? [$request->getSchemeAndHttpHost() . '/' . ltrim($line->product->getImage(), '/')]
                                : [],
                        ],
                    ],
                    'quantity' => $line->quantity,
                ];
            }

            if ($totals->shipping > 0) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'usd',
                        'unit_amount' => $totals->shipping,
                        'product_data' => ['name' => 'Shipping'],
                    ],
                    'quantity' => 1,
                ];
            }

            if ($totals->tax > 0) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'usd',
                        'unit_amount' => $totals->tax,
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

        $this->cart->clear();

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
            // Never cancel an order that was paid meanwhile (e.g. confirmed by the webhook).
            if ($order && $order->getStatus()?->isCancellable()) {
                $order->setStatus(OrderStatus::Cancelled);
                $this->em->flush();
            }
            $this->session->remove('order_id');
        }

        $this->addFlash('error', 'Paiement annulé.');
        return $this->redirectToRoute('app_checkout');
    }

    // Stock may have changed since the products were added: never sell what is not available.
    private function redirectIfStockIsShort(): ?Response
    {
        $unavailable = $this->cart->findUnavailableProducts();
        if ($unavailable === []) {
            return null;
        }

        foreach ($unavailable as $product) {
            $this->addFlash('error', sprintf(
                'Stock insuffisant pour « %s » (%d disponible(s)) : ajustez la quantité.',
                $product->getName(),
                $product->getStock()
            ));
        }

        return $this->redirectToRoute('app_cart');
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
        if ($order->getStatus() !== OrderStatus::Paid) {
            $order->setStatus(OrderStatus::Paid);
            $this->em->flush();
        }
    }
}
