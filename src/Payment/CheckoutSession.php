<?php

namespace App\Payment;

/**
 * Ce que la boutique retient d'une session de paiement, indépendamment du prestataire.
 */
final class CheckoutSession
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $url,
        public readonly bool $paid,
        public readonly int $amountTotal,
        public readonly string $currency,
        public readonly ?string $orderNumber,
        public readonly ?string $paymentIntentId = null,
    ) {
    }
}
