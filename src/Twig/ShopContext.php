<?php

namespace App\Twig;

use App\Entity\User;
use App\Payment\PaymentGateway;
use App\Repository\CartRepository;
use App\Repository\CategoryRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Informations communes à toutes les pages (variable Twig « shop »), calculées à la demande.
 */
final class ShopContext
{
    private ?int $cartCount = null;
    private ?array $categories = null;

    public function __construct(
        private readonly Security $security,
        private readonly CartRepository $cartRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly PaymentGateway $gateway,
        private readonly string $brandUrl,
    ) {
    }

    public function getCartCount(): int
    {
        if (null === $this->cartCount) {
            $user = $this->security->getUser();
            $cart = $user instanceof User ? $this->cartRepository->findByUser($user) : null;
            $this->cartCount = $cart?->getTotalItems() ?? 0;
        }

        return $this->cartCount;
    }

    /**
     * @return array<int, array{category: \App\Entity\Category, count: int}>
     */
    public function getCategories(): array
    {
        return $this->categories ??= $this->categoryRepository->findWithActiveCounts();
    }

    /** Aucun débit réel : les clés de paiement sont des clés de test, ou absentes. */
    public function isDemo(): bool
    {
        return !$this->gateway->isConfigured() || $this->gateway->isTestMode();
    }

    public function isPaymentAvailable(): bool
    {
        return $this->gateway->isConfigured();
    }

    public function getBrandUrl(): string
    {
        return $this->brandUrl;
    }
}
