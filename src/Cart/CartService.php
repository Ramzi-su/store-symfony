<?php

namespace App\Cart;

use App\Cart\Exception\OutOfStockException;
use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CartItemRepository;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Single entry point for the shopping cart.
 *
 * Guests keep their cart in the session, logged-in users in the database
 * (Cart + CartItem). Controllers and templates only see CartLine / CartTotals
 * and never need to know which storage is used.
 */
class CartService
{
    private const SESSION_KEY = 'cart';
    public const MAX_QUANTITY = 99;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
        private readonly CartRepository $cartRepository,
        private readonly CartItemRepository $cartItemRepository,
        private readonly ProductRepository $productRepository,
    ) {
    }

    /**
     * @return list<CartLine>
     */
    public function getLines(): array
    {
        $user = $this->getUser();
        if ($user) {
            return array_map(
                fn (CartItem $item) => new CartLine($item->getProduct(), $item->getQuantity(), $item->getColor(), $item->getStorage()),
                $this->cartItemRepository->findBy(['user' => $user])
            );
        }

        $lines = [];
        foreach ($this->getSessionCart() as $key => $entry) {
            // Entries written before product_id was stored only have it in the key.
            $productId = (int) ($entry['product_id'] ?? explode('-', (string) $key)[0]);
            $product = $this->productRepository->find($productId);

            // A product deleted since it was added is silently dropped from the cart.
            if ($product) {
                $lines[] = new CartLine($product, (int) $entry['quantity'], $entry['color'] ?? null, $entry['storage'] ?? null);
            }
        }

        return $lines;
    }

    public function getTotals(): CartTotals
    {
        $subtotal = 0;
        foreach ($this->getLines() as $line) {
            $subtotal += $line->getSubtotal();
        }

        return CartTotals::fromSubtotal($subtotal);
    }

    public function isEmpty(): bool
    {
        return $this->getLines() === [];
    }

    /**
     * Number of articles, for the header badge.
     */
    public function countItems(): int
    {
        $user = $this->getUser();
        if ($user) {
            return array_sum(array_map(fn (CartItem $item) => $item->getQuantity(), $this->cartItemRepository->findBy(['user' => $user])));
        }

        return array_sum(array_map(fn (array $entry) => (int) ($entry['quantity'] ?? 1), $this->getSessionCart()));
    }

    /**
     * Adds up to $quantity units, never more than the stock allows.
     *
     * @return int the number of units actually added (less than requested when stock is short)
     *
     * @throws OutOfStockException when no unit at all can be added
     */
    public function add(Product $product, int $quantity, ?string $color, ?string $storage): int
    {
        $current = $this->lineQuantity($product, $color, $storage);
        $allowed = $this->allowedLineQuantity($product, $current);
        $final = min($current + max(1, $quantity), $allowed);

        $this->saveLine($product, $final, $color, $storage);

        return $final - $current;
    }

    /**
     * Sets the quantity of a cart line, capped by the stock.
     *
     * @return int the quantity actually stored
     *
     * @throws OutOfStockException when the product is no longer in stock
     */
    public function update(Product $product, int $quantity, ?string $color, ?string $storage): int
    {
        $current = $this->lineQuantity($product, $color, $storage);
        if ($current === 0) {
            return 0;
        }

        $final = min(max(1, $quantity), $this->allowedLineQuantity($product, $current));
        $this->saveLine($product, $final, $color, $storage);

        return $final;
    }

    /**
     * Products whose quantity in the cart (all options together) exceeds the current stock.
     * Stock can change between adding to the cart and paying, so checkout checks again.
     *
     * @return list<Product>
     */
    public function findUnavailableProducts(): array
    {
        $quantities = [];
        $products = [];
        foreach ($this->getLines() as $line) {
            $id = $line->product->getId();
            $quantities[$id] = ($quantities[$id] ?? 0) + $line->quantity;
            $products[$id] = $line->product;
        }

        return array_values(array_filter(
            $products,
            fn (Product $product) => $quantities[$product->getId()] > $product->getStock()
        ));
    }

    public function remove(Product $product, ?string $color, ?string $storage): void
    {
        $user = $this->getUser();

        if ($user) {
            $item = $this->findItem($user, $product, $color, $storage);
            if ($item) {
                $this->em->remove($item);
                $this->em->flush();
            }

            return;
        }

        $cart = $this->getSessionCart();
        unset($cart[$this->sessionKey($product, $color, $storage)]);
        $this->saveSessionCart($cart);
    }

    public function clear(): void
    {
        $user = $this->getUser();

        if ($user) {
            foreach ($this->cartItemRepository->findBy(['user' => $user]) as $item) {
                $this->em->remove($item);
            }
            $this->em->flush();

            return;
        }

        $this->requestStack->getSession()->remove(self::SESSION_KEY);
    }

    /**
     * Highest quantity this line may hold: the stock minus the units of the same
     * product already in other lines (other color/storage), and at most MAX_QUANTITY.
     *
     * @throws OutOfStockException
     */
    private function allowedLineQuantity(Product $product, int $currentLineQuantity): int
    {
        $inOtherLines = $this->productQuantity($product) - $currentLineQuantity;
        $allowed = min(self::MAX_QUANTITY, (int) $product->getStock() - $inOtherLines);

        if ($allowed < 1) {
            throw new OutOfStockException($product);
        }

        return $allowed;
    }

    // Units of this product in the whole cart, all options together.
    private function productQuantity(Product $product): int
    {
        $total = 0;
        foreach ($this->getLines() as $line) {
            if ($line->product->getId() === $product->getId()) {
                $total += $line->quantity;
            }
        }

        return $total;
    }

    private function lineQuantity(Product $product, ?string $color, ?string $storage): int
    {
        $user = $this->getUser();
        if ($user) {
            return $this->findItem($user, $product, $color, $storage)?->getQuantity() ?? 0;
        }

        return (int) ($this->getSessionCart()[$this->sessionKey($product, $color, $storage)]['quantity'] ?? 0);
    }

    // Creates or updates the line in the right storage.
    private function saveLine(Product $product, int $quantity, ?string $color, ?string $storage): void
    {
        $user = $this->getUser();

        if ($user) {
            $item = $this->findItem($user, $product, $color, $storage);
            if (!$item) {
                $item = (new CartItem())
                    ->setUser($user)
                    ->setProduct($product)
                    ->setColor($color)
                    ->setStorage($storage);
                $this->getOrCreateCart($user)->addItem($item);
                $this->em->persist($item);
            }
            $item->setQuantity($quantity);
            $this->em->flush();

            return;
        }

        $cart = $this->getSessionCart();
        $cart[$this->sessionKey($product, $color, $storage)] = [
            'product_id' => $product->getId(),
            'quantity' => $quantity,
            'color' => $color,
            'storage' => $storage,
        ];
        $this->saveSessionCart($cart);
    }

    private function getUser(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }

    private function findItem(User $user, Product $product, ?string $color, ?string $storage): ?CartItem
    {
        return $this->cartItemRepository->findOneBy([
            'user' => $user,
            'product' => $product,
            'color' => $color,
            'storage' => $storage,
        ]);
    }

    // CartItem.cart is NOT NULL: a user's first item needs a Cart row.
    private function getOrCreateCart(User $user): Cart
    {
        $cart = $this->cartRepository->findOneBy(['user' => $user]);
        if (!$cart) {
            $cart = (new Cart())->setUser($user)->setCreatedAt(new \DateTimeImmutable());
            $this->em->persist($cart);
        }

        return $cart;
    }

    private function sessionKey(Product $product, ?string $color, ?string $storage): string
    {
        return $product->getId() . '-' . ($color ?? '') . '-' . ($storage ?? '');
    }

    /**
     * @return array<string, array{product_id?: int, quantity: int, color?: ?string, storage?: ?string}>
     */
    private function getSessionCart(): array
    {
        return $this->requestStack->getSession()->get(self::SESSION_KEY, []);
    }

    private function saveSessionCart(array $cart): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $cart);
    }
}
