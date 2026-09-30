<?php

namespace App\Entity;

use App\Repository\OrdersRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\User;
use App\Entity\OrderItems;
use App\Enum\OrderStatus;

#[ORM\Entity(repositoryClass: OrdersRepository::class)]
#[ORM\Table(name: "orders")]
class Orders
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    // Nullable: guests can check out with a session cart.
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "id", nullable: true)]
    private ?User $user = null;

    // All amounts are in cents.
    #[ORM\Column]
    private ?int $total = null;

    #[ORM\Column(length: 255, enumType: OrderStatus::class)]
    private ?OrderStatus $status = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(nullable: true)]
    private ?int $subtotal = null;

    #[ORM\Column(nullable: true)]
    private ?int $tax = null;

    #[ORM\Column(nullable: true)]
    private ?int $shippingCost = null;

    // Customer details typed in the checkout form. Columns are nullable because orders
    // created before this migration have none; the "checkout" validation group makes
    // them mandatory for every new order.
    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\NotBlank(groups: ['checkout'])]
    #[Assert\Length(max: 100, groups: ['checkout'])]
    private ?string $firstName = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\NotBlank(groups: ['checkout'])]
    #[Assert\Length(max: 100, groups: ['checkout'])]
    private ?string $lastName = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\NotBlank(groups: ['checkout'])]
    #[Assert\Email(groups: ['checkout'])]
    #[Assert\Length(max: 180, groups: ['checkout'])]
    private ?string $email = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\NotBlank(groups: ['checkout'])]
    #[Assert\Regex(pattern: '/^\+?[0-9 .\-()]{6,30}$/', message: 'Numéro de téléphone invalide.', groups: ['checkout'])]
    private ?string $phoneNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(groups: ['checkout'])]
    #[Assert\Length(max: 255, groups: ['checkout'])]
    private ?string $address = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\NotBlank(groups: ['checkout'])]
    #[Assert\Length(max: 100, groups: ['checkout'])]
    private ?string $city = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100, groups: ['checkout'])]
    private ?string $state = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\NotBlank(groups: ['checkout'])]
    #[Assert\Length(max: 20, groups: ['checkout'])]
    private ?string $postcode = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\NotBlank(groups: ['checkout'])]
    #[Assert\Length(max: 100, groups: ['checkout'])]
    private ?string $country = null;

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    private ?string $stripeSessionId = null;

    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderItems::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getTotal(): ?int { return $this->total; }
    public function setTotal(int $total): static { $this->total = $total; return $this; }

    public function getStatus(): ?OrderStatus { return $this->status; }
    public function setStatus(OrderStatus $status): static { $this->status = $status; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->created_at; }
    public function setCreatedAt(\DateTimeImmutable $created_at): static { $this->created_at = $created_at; return $this; }

    public function getSubtotal(): ?int { return $this->subtotal; }
    public function setSubtotal(?int $subtotal): static { $this->subtotal = $subtotal; return $this; }

    public function getTax(): ?int { return $this->tax; }
    public function setTax(?int $tax): static { $this->tax = $tax; return $this; }

    public function getShippingCost(): ?int { return $this->shippingCost; }
    public function setShippingCost(?int $shippingCost): static { $this->shippingCost = $shippingCost; return $this; }

    public function getFirstName(): ?string { return $this->firstName; }
    public function setFirstName(?string $firstName): static { $this->firstName = $firstName; return $this; }

    public function getLastName(): ?string { return $this->lastName; }
    public function setLastName(?string $lastName): static { $this->lastName = $lastName; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): static { $this->email = $email; return $this; }

    public function getPhoneNumber(): ?string { return $this->phoneNumber; }
    public function setPhoneNumber(?string $phoneNumber): static { $this->phoneNumber = $phoneNumber; return $this; }

    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): static { $this->address = $address; return $this; }

    public function getCity(): ?string { return $this->city; }
    public function setCity(?string $city): static { $this->city = $city; return $this; }

    public function getState(): ?string { return $this->state; }
    public function setState(?string $state): static { $this->state = $state; return $this; }

    public function getPostcode(): ?string { return $this->postcode; }
    public function setPostcode(?string $postcode): static { $this->postcode = $postcode; return $this; }

    public function getCountry(): ?string { return $this->country; }
    public function setCountry(?string $country): static { $this->country = $country; return $this; }

    public function getStripeSessionId(): ?string { return $this->stripeSessionId; }
    public function setStripeSessionId(?string $stripeSessionId): static { $this->stripeSessionId = $stripeSessionId; return $this; }

    /**
     * @return Collection<int, OrderItems>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(OrderItems $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items[] = $item;
            $item->setOrder($this);
        }

        return $this;
    }

    public function removeItem(OrderItems $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getOrder() === $this) {
                $item->setOrder(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return 'Commande #' . $this->id;
    }
}
