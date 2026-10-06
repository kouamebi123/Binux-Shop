<?php

namespace App\Payment;

use App\Entity\Order;
use App\Util\Money;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

final class StripeGateway implements PaymentGateway
{
    /** Durée de vie d'une session de paiement (minimum accepté par Stripe : 30 minutes). */
    public const SESSION_LIFETIME = 1800;

    private ?StripeClient $client = null;

    public function __construct(
        private readonly string $secretKey,
        private readonly string $webhookSecret,
    ) {
    }

    public function isConfigured(): bool
    {
        return 1 === preg_match('/^(sk|rk)_(test|live)_[A-Za-z0-9]{16,}$/', $this->secretKey);
    }

    public function isTestMode(): bool
    {
        return !str_contains($this->secretKey, '_live_');
    }

    public function canVerifyWebhooks(): bool
    {
        return str_starts_with($this->webhookSecret, 'whsec_');
    }

    public function createCheckout(Order $order, string $successUrl, string $cancelUrl): CheckoutSession
    {
        $lineItems = [];
        foreach ($order->getItems() as $item) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => ['name' => $item->getProductName()],
                    'unit_amount' => Money::toCents($item->getPrice()),
                ],
                'quantity' => $item->getQuantity(),
            ];
        }

        try {
            $session = $this->client()->checkout->sessions->create([
                'mode' => 'payment',
                'locale' => 'fr',
                'line_items' => $lineItems,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'client_reference_id' => $order->getOrderNumber(),
                'customer_email' => $order->getUser()?->getEmail(),
                'expires_at' => time() + self::SESSION_LIFETIME + 60,
                'metadata' => ['order_number' => $order->getOrderNumber()],
            ]);
        } catch (ApiErrorException $e) {
            throw new PaymentException($e->getMessage(), 0, $e);
        }

        return $this->toCheckoutSession($session);
    }

    public function fetchCheckout(string $sessionId): CheckoutSession
    {
        try {
            return $this->toCheckoutSession($this->client()->checkout->sessions->retrieve($sessionId));
        } catch (ApiErrorException $e) {
            throw new PaymentException($e->getMessage(), 0, $e);
        }
    }

    public function parseWebhook(string $payload, string $signature): ?CheckoutSession
    {
        try {
            $event = Webhook::constructEvent($payload, $signature, $this->webhookSecret);
        } catch (SignatureVerificationException|\UnexpectedValueException $e) {
            throw new PaymentException('Signature de notification invalide.', 0, $e);
        }

        if (!\in_array($event->type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            return null;
        }

        $session = $event->data->object;

        return $session instanceof Session ? $this->toCheckoutSession($session) : null;
    }

    private function toCheckoutSession(Session $session): CheckoutSession
    {
        return new CheckoutSession(
            id: (string) $session->id,
            url: $session->url,
            paid: 'paid' === $session->payment_status,
            amountTotal: (int) $session->amount_total,
            currency: strtolower((string) $session->currency),
            orderNumber: $session->client_reference_id,
            paymentIntentId: \is_string($session->payment_intent) ? $session->payment_intent : null,
        );
    }

    private function client(): StripeClient
    {
        if (!$this->isConfigured()) {
            throw new PaymentException('Les clés Stripe ne sont pas renseignées.');
        }

        return $this->client ??= new StripeClient($this->secretKey);
    }
}
