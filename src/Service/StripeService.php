<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\Payment;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;

class StripeService
{
    private EntityManagerInterface $entityManager;
    private PaymentRepository $paymentRepository;
    private string $stripeSecretKey;
    private string $stripePublicKey;

    public function __construct(
        EntityManagerInterface $entityManager,
        PaymentRepository $paymentRepository,
        string $stripeSecretKey,
        string $stripePublicKey
    ) {
        $this->entityManager = $entityManager;
        $this->paymentRepository = $paymentRepository;
        $this->stripeSecretKey = $stripeSecretKey;
        $this->stripePublicKey = $stripePublicKey;
        
        Stripe::setApiKey($this->stripeSecretKey);
    }

    public function createCheckoutSession(Order $order, string $successUrl, string $cancelUrl): Session
    {
        $lineItems = [];

        foreach ($order->getItems() as $item) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => $item->getProductName(),
                    ],
                    'unit_amount' => (int)((float)$item->getPrice() * 100), // Stripe utilise les centimes
                ],
                'quantity' => $item->getQuantity(),
            ];
        }

        try {
            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => $lineItems,
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'client_reference_id' => $order->getOrderNumber(),
                'metadata' => [
                    'order_id' => $order->getId(),
                    'order_number' => $order->getOrderNumber(),
                ],
            ]);

            // Créer l'enregistrement de paiement
            $payment = new Payment();
            $payment->setOrderRef($order);
            $payment->setStripePaymentIntentId($session->payment_intent ?? $session->id);
            $payment->setAmount($order->getTotal());
            $payment->setCurrency('eur');
            $payment->setStatus(Payment::STATUS_PENDING);

            $this->entityManager->persist($payment);
            $this->entityManager->flush();

            return $session;
        } catch (ApiErrorException $e) {
            throw new \Exception('Erreur lors de la création de la session de paiement : ' . $e->getMessage());
        }
    }

    public function handlePaymentSuccess(string $sessionId): ?Payment
    {
        try {
            $session = Session::retrieve($sessionId);
            
            if ($session->payment_status === 'paid') {
                $orderId = $session->metadata->order_id ?? null;
                
                if ($orderId) {
                    $payment = $this->paymentRepository->findOneBy(['orderRef' => $orderId]);
                    
                    if ($payment) {
                        $payment->setStatus(Payment::STATUS_SUCCEEDED);
                        $payment->setStripeData(json_encode($session));
                        
                        // Mettre à jour le statut de la commande
                        $order = $payment->getOrderRef();
                        $order->setStatus(Order::STATUS_PROCESSING);
                        
                        $this->entityManager->flush();
                        
                        return $payment;
                    }
                }
            }
            
            return null;
        } catch (ApiErrorException $e) {
            throw new \Exception('Erreur lors de la vérification du paiement : ' . $e->getMessage());
        }
    }

    public function getPublicKey(): string
    {
        return $this->stripePublicKey;
    }
}

