<?php

namespace App\Controller;

use App\Entity\User;
use App\Exception\ShopException;
use App\Payment\PaymentException;
use App\Payment\PaymentGateway;
use App\Service\OrderService;
use App\Service\PaymentService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PaymentController extends AbstractController
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly OrderService $orderService,
    ) {
    }

    /**
     * Ouvre le paiement d'une commande. En POST uniquement : afficher une page ne crée rien chez Stripe.
     */
    #[Route('/paiement/commande/{orderNumber}', name: 'app_payment_checkout', methods: ['GET', 'POST'])]
    public function checkout(string $orderNumber, Request $request): Response
    {
        $order = $this->orderService->findUserOrder($this->user(), $orderNumber);

        if (!$order) {
            throw $this->createNotFoundException('Commande non trouvée');
        }

        $orderPage = $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);

        if (!$request->isMethod('POST')) {
            return $orderPage;
        }

        if (!$this->isCsrfTokenValid('pay_' . $order->getOrderNumber(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide');
        }

        try {
            return $this->redirect($this->paymentService->startCheckout(
                $order,
                $this->generateUrl('app_payment_success', [], UrlGeneratorInterface::ABSOLUTE_URL) . '?session_id={CHECKOUT_SESSION_ID}',
                $this->generateUrl('app_payment_cancel', ['orderNumber' => $orderNumber], UrlGeneratorInterface::ABSOLUTE_URL),
            ), Response::HTTP_SEE_OTHER);
        } catch (ShopException $e) {
            $this->addFlash('error', $e->getMessage());

            return $orderPage;
        }
    }

    #[Route('/paiement/succes', name: 'app_payment_success', methods: ['GET'])]
    public function success(Request $request): Response
    {
        $payment = $this->paymentService->confirmFromSessionId((string) $request->query->get('session_id', ''));

        // Une session ne confirme que la commande de la personne connectée.
        if (!$payment || $payment->getOrderRef()->getUser() !== $this->user()) {
            $this->addFlash('warning', 'Le paiement n\'est pas encore confirmé. S\'il a bien été validé, votre commande sera mise à jour sous peu.');

            return $this->redirectToRoute('app_order_list');
        }

        $this->addFlash('success', 'Paiement reçu. Votre commande est confirmée.');

        return $this->redirectToRoute('app_order_confirmation', [
            'orderNumber' => $payment->getOrderRef()->getOrderNumber(),
        ]);
    }

    #[Route('/paiement/annule/{orderNumber}', name: 'app_payment_cancel', methods: ['GET'])]
    public function cancel(string $orderNumber): Response
    {
        $order = $this->orderService->findUserOrder($this->user(), $orderNumber);

        if (!$order) {
            return $this->redirectToRoute('app_order_list');
        }

        $this->addFlash('warning', 'Le paiement n\'a pas été effectué. Votre commande est conservée, vous pouvez la régler quand vous voulez.');

        return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
    }

    /**
     * Notification envoyée par Stripe quand un paiement aboutit, même si le client a fermé son navigateur.
     * Seules les notifications dont la signature est valide sont prises en compte.
     */
    #[Route('/stripe/webhook', name: 'app_stripe_webhook', methods: ['POST'])]
    public function webhook(Request $request, PaymentGateway $gateway, LoggerInterface $logger): Response
    {
        if (!$gateway->canVerifyWebhooks()) {
            throw $this->createNotFoundException();
        }

        try {
            $session = $gateway->parseWebhook($request->getContent(), (string) $request->headers->get('Stripe-Signature'));
        } catch (PaymentException $e) {
            $logger->warning('Notification de paiement rejetée.', ['error' => $e->getMessage()]);

            return new Response('signature invalide', Response::HTTP_BAD_REQUEST);
        }

        if ($session) {
            $this->paymentService->confirm($session);
        }

        return new Response('ok');
    }

    private function user(): User
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
