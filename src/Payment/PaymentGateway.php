<?php

namespace App\Payment;

use App\Entity\Order;

interface PaymentGateway
{
    /** Les clés du prestataire sont-elles renseignées ? */
    public function isConfigured(): bool;

    /** Vrai tant que les clés sont des clés de test : aucun débit réel. */
    public function isTestMode(): bool;

    /** La signature des notifications (webhook) peut-elle être vérifiée ? */
    public function canVerifyWebhooks(): bool;

    /**
     * @throws PaymentException
     */
    public function createCheckout(Order $order, string $successUrl, string $cancelUrl): CheckoutSession;

    /**
     * @throws PaymentException
     */
    public function fetchCheckout(string $sessionId): CheckoutSession;

    /**
     * Vérifie la signature d'une notification et renvoie la session concernée,
     * ou null si l'événement ne concerne pas un paiement abouti.
     *
     * @throws PaymentException si la signature est invalide
     */
    public function parseWebhook(string $payload, string $signature): ?CheckoutSession;
}
