<?php

namespace App\Entity;

use App\Repository\CartRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Util\Money;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CartRepository::class)]
class Cart
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'cart', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\OneToMany(mappedBy: 'cart', targetEntity: CartItem::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $cartItems;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->cartItems = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, CartItem>
     */
    public function getItems(): Collection
    {
        return $this->cartItems;
    }

    public function addItem(CartItem $item): self
    {
        if (!$this->cartItems->contains($item)) {
            $this->cartItems->add($item);
            $item->setCart($this);
        }

        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function removeItem(CartItem $item): self
    {
        if ($this->cartItems->removeElement($item)) {
            if ($item->getCart() === $this) {
                $item->setCart(null);
            }
        }

        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function clear(): self
    {
        $this->cartItems->clear();
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getTotalCents(): int
    {
        $total = 0;

        foreach ($this->cartItems as $item) {
            $total += $item->getTotalCents();
        }

        return $total;
    }

    public function getTotal(): string
    {
        return Money::toDecimal($this->getTotalCents());
    }

    public function getTotalItems(): int
    {
        $total = 0;

        foreach ($this->cartItems as $item) {
            $total += $item->getQuantity();
        }

        return $total;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}

