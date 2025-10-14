<?php

namespace App\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use App\Repository\PaymentRepository;
use App\Service\StripeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/paiement')]
#[IsGranted('ROLE_USER')]
class PaymentController extends AbstractController
{
    private StripeService $stripeService;
    private OrderRepository $orderRepository;
    private PaymentRepository $paymentRepository;

    public function __construct(
        StripeService $stripeService,
        OrderRepository $orderRepository,
        PaymentRepository $paymentRepository
    ) {
        $this->stripeService = $stripeService;
        $this->orderRepository = $orderRepository;
        $this->paymentRepository = $paymentRepository;
    }

    #[Route('/commande/{orderNumber}', name: 'app_payment_checkout')]
    public function checkout(string $orderNumber): Response
    {
        $user = $this->getUser();
        $orders = $this->orderRepository->findByUser($user);

        $order = null;
        foreach ($orders as $o) {
            if ($o->getOrderNumber() === $orderNumber) {
                $order = $o;
                break;
            }
        }

        if (!$order) {
            throw $this->createNotFoundException('Commande non trouvée');
        }

        // Vérifier si la commande n'est pas déjà payée
        $payment = $this->paymentRepository->findOneBy(['orderRef' => $order]);
        if ($payment && $payment->isPaid()) {
            $this->addFlash('info', 'Cette commande a déjà été payée');
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        try {
            $successUrl = $this->generateUrl('app_payment_success', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL) . '?session_id={CHECKOUT_SESSION_ID}';
            $cancelUrl = $this->generateUrl('app_payment_cancel', ['orderNumber' => $orderNumber], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);

            $session = $this->stripeService->createCheckoutSession($order, $successUrl, $cancelUrl);

            return $this->render('payment/checkout.html.twig', [
                'order' => $order,
                'stripe_public_key' => $this->stripeService->getPublicKey(),
                'checkout_session_id' => $session->id,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }
    }

    #[Route('/succes', name: 'app_payment_success')]
    public function success(Request $request): Response
    {
        $sessionId = $request->query->get('session_id');

        if (!$sessionId) {
            return $this->redirectToRoute('app_home');
        }

        try {
            $payment = $this->stripeService->handlePaymentSuccess($sessionId);
            
            if ($payment) {
                $this->addFlash('success', 'Paiement effectué avec succès ! Votre commande est confirmée.');
                return $this->redirectToRoute('app_order_confirmation', [
                    'orderNumber' => $payment->getOrderRef()->getOrderNumber()
                ]);
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la vérification du paiement');
        }

        return $this->redirectToRoute('app_order_list');
    }

    #[Route('/annule/{orderNumber}', name: 'app_payment_cancel')]
    public function cancel(string $orderNumber): Response
    {
        $this->addFlash('warning', 'Le paiement a été annulé. Vous pouvez réessayer quand vous le souhaitez.');
        
        $user = $this->getUser();
        $orders = $this->orderRepository->findByUser($user);

        $order = null;
        foreach ($orders as $o) {
            if ($o->getOrderNumber() === $orderNumber) {
                $order = $o;
                break;
            }
        }

        if ($order) {
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        return $this->redirectToRoute('app_order_list');
    }
}

