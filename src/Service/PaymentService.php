<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\Payment;
use App\Exception\ShopException;
use App\Payment\CheckoutSession;
use App\Payment\PaymentException;
use App\Payment\PaymentGateway;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class PaymentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PaymentRepository $paymentRepository,
        private readonly PaymentGateway $gateway,
        private readonly OrderService $orderService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Ouvre une session de paiement pour la commande et renvoie l'adresse où envoyer le client.
     */
    public function startCheckout(Order $order, string $successUrl, string $cancelUrl): string
    {
        if ($order->isPaid()) {
            throw new ShopException('Cette commande est déjà payée.');
        }

        if (!$order->isAwaitingPayment()) {
            throw new ShopException('Cette commande n\'attend plus de paiement.');
        }

        if (!$this->gateway->isConfigured()) {
            throw new ShopException('Le paiement en ligne n\'est pas disponible pour le moment.');
        }

        try {
            $session = $this->gateway->createCheckout($order, $successUrl, $cancelUrl);
        } catch (PaymentException $e) {
            $this->logger->error('Création de la session de paiement impossible.', [
                'order' => $order->getOrderNumber(),
                'error' => $e->getMessage(),
            ]);

            throw new ShopException('Le paiement n\'a pas pu démarrer. Réessayez dans un instant.');
        }

        if (!$session->url) {
            throw new ShopException('Le paiement n\'a pas pu démarrer. Réessayez dans un instant.');
        }

        // Une seule ligne de paiement par commande : un nouvel essai remplace la session précédente.
        $payment = $order->getPayment() ?? $this->paymentRepository->findOneBy(['orderRef' => $order]) ?? new Payment();
        $payment->setOrderRef($order);
        $payment->setStripePaymentIntentId($session->id);
        $payment->setAmount($order->getTotal());
        $payment->setCurrency('eur');
        $payment->setStatus(Payment::STATUS_PENDING);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $session->url;
    }

    /**
     * Vérifie auprès du prestataire qu'une session a bien été payée, puis confirme la commande.
     * Renvoie null si la session est inconnue de la boutique ou pas encore réglée.
     */
    public function confirmFromSessionId(string $sessionId): ?Payment
    {
        if (!preg_match('/^cs_[A-Za-z0-9_]{8,250}$/', $sessionId)) {
            return null;
        }

        $payment = $this->paymentRepository->findOneBy(['stripePaymentIntentId' => $sessionId]);
        if (!$payment) {
            return null;
        }

        if ($payment->isPaid()) {
            return $payment;
        }

        try {
            $session = $this->gateway->fetchCheckout($sessionId);
        } catch (PaymentException $e) {
            $this->logger->error('Vérification du paiement impossible.', ['error' => $e->getMessage()]);

            return null;
        }

        return $this->confirm($session);
    }

    /**
     * Confirme la commande à partir d'une session dont l'origine est déjà authentifiée
     * (relue chez le prestataire ou reçue dans une notification signée).
     */
    public function confirm(CheckoutSession $session): ?Payment
    {
        $payment = $this->paymentRepository->findOneBy(['stripePaymentIntentId' => $session->id]);
        if (!$payment) {
            return null;
        }

        if ($payment->isPaid()) {
            return $payment;
        }

        if (!$session->paid) {
            return null;
        }

        $order = $payment->getOrderRef();

        // Le montant encaissé doit être exactement celui de la commande.
        if ($session->orderNumber !== $order->getOrderNumber()
            || 'eur' !== $session->currency
            || $session->amountTotal !== $order->getTotalCents()
        ) {
            $this->logger->critical('Paiement incohérent avec la commande : non confirmé.', [
                'order' => $order->getOrderNumber(),
                'expected_cents' => $order->getTotalCents(),
                'received_cents' => $session->amountTotal,
                'currency' => $session->currency,
            ]);

            return null;
        }

        return $this->entityManager->wrapInTransaction(function () use ($payment, $order, $session): Payment {
            if ($order->isCancelled()) {
                $this->orderService->reviveCancelledOrder($order);
            }

            $payment->setStatus(Payment::STATUS_SUCCEEDED);
            $payment->setStripeData(json_encode([
                'session' => $session->id,
                'payment_intent' => $session->paymentIntentId,
                'amount_total' => $session->amountTotal,
                'currency' => $session->currency,
            ], \JSON_THROW_ON_ERROR));

            $order->setPayment($payment);
            if ($order->isPending() || $order->isCancelled()) {
                $order->setStatus(Order::STATUS_PROCESSING);
            }

            $this->entityManager->flush();

            return $payment;
        });
    }
}
