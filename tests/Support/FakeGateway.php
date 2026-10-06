<?php

namespace App\Tests\Support;

use App\Entity\Order;
use App\Payment\CheckoutSession;
use App\Payment\PaymentException;
use App\Payment\PaymentGateway;

/**
 * Passerelle de paiement simulée : mémorise les sessions créées et laisse le test décider de ce que « Stripe » répond.
 * L'état est statique pour survivre au redémarrage du noyau entre deux requêtes d'un même test.
 */
final class FakeGateway implements PaymentGateway
{
    /** @var array<string, CheckoutSession> */
    public static array $sessions = [];
    public static bool $configured = true;
    public static bool $webhooks = true;
    private static int $sequence = 0;

    public static function reset(): void
    {
        self::$sessions = [];
        self::$configured = true;
        self::$webhooks = true;
    }

    /** Fait passer une session à « payée », avec le montant que Stripe déclarerait. */
    public static function pay(string $sessionId, ?int $amountTotal = null, string $currency = 'eur'): CheckoutSession
    {
        $session = self::$sessions[$sessionId];

        return self::$sessions[$sessionId] = new CheckoutSession(
            $session->id,
            $session->url,
            true,
            $amountTotal ?? $session->amountTotal,
            $currency,
            $session->orderNumber,
            'pi_test_' . substr($sessionId, -6),
        );
    }

    public static function lastSessionId(): ?string
    {
        return array_key_last(self::$sessions);
    }

    public function isConfigured(): bool
    {
        return self::$configured;
    }

    public function isTestMode(): bool
    {
        return true;
    }

    public function canVerifyWebhooks(): bool
    {
        return self::$webhooks;
    }

    public function createCheckout(Order $order, string $successUrl, string $cancelUrl): CheckoutSession
    {
        $id = sprintf('cs_test_%08d', ++self::$sequence);

        return self::$sessions[$id] = new CheckoutSession(
            $id,
            'https://checkout.stripe.com/c/pay/' . $id,
            false,
            $order->getTotalCents(),
            'eur',
            $order->getOrderNumber(),
        );
    }

    public function fetchCheckout(string $sessionId): CheckoutSession
    {
        return self::$sessions[$sessionId] ?? throw new PaymentException('Session inconnue.');
    }

    public function parseWebhook(string $payload, string $signature): ?CheckoutSession
    {
        if ('signature-valide' !== $signature) {
            throw new PaymentException('Signature de notification invalide.');
        }

        return self::$sessions[$payload] ?? null;
    }
}
