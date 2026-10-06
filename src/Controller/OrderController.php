<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Order;
use App\Entity\User;
use App\Exception\ShopException;
use App\Form\OrderType;
use App\Repository\OrderRepository;
use App\Service\CartService;
use App\Service\OrderService;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/commande')]
#[IsGranted('ROLE_USER')]
class OrderController extends AbstractController
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly CartService $cartService,
        private readonly OrderRepository $orderRepository,
    ) {
    }

    #[Route('/creer', name: 'app_order_create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, PaymentService $paymentService): Response
    {
        $user = $this->user();
        $cart = $this->cartService->getCart($user);

        $notices = $this->cartService->refresh($cart);
        if ($notices) {
            foreach ($notices as $notice) {
                $this->addFlash('warning', $notice);
            }

            return $this->redirectToRoute('app_cart_index');
        }

        if ($cart->getItems()->isEmpty()) {
            $this->addFlash('error', 'Votre panier est vide.');

            return $this->redirectToRoute('app_cart_index');
        }

        $address = $this->defaultAddress($user) ?? new Address();

        $form = $this->createForm(OrderType::class, [
            'address' => $address,
            'notes' => null,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            /** @var Address $addressData */
            $addressData = $data['address'];

            if (!$addressData->getId()) {
                $addressData->setUser($user);
                $addressData->setIsDefault(true);
                $entityManager->persist($addressData);
            }
            $entityManager->flush();

            try {
                $order = $this->orderService->createOrderFromCart($cart, $addressData, $data['notes']);
            } catch (ShopException $e) {
                $this->addFlash('error', $e->getMessage());

                return $this->redirectToRoute('app_cart_index');
            }

            // La commande est enregistrée : on enchaîne directement sur le paiement.
            try {
                return $this->redirect($paymentService->startCheckout(
                    $order,
                    $this->generateUrl('app_payment_success', [], UrlGeneratorInterface::ABSOLUTE_URL) . '?session_id={CHECKOUT_SESSION_ID}',
                    $this->generateUrl('app_payment_cancel', ['orderNumber' => $order->getOrderNumber()], UrlGeneratorInterface::ABSOLUTE_URL),
                ), Response::HTTP_SEE_OTHER);
            } catch (ShopException $e) {
                $this->addFlash('warning', 'Votre commande est enregistrée. ' . $e->getMessage());

                return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
            }
        }

        return $this->render('order/create.html.twig', [
            'cart' => $cart,
            'form' => $form,
        ]);
    }

    #[Route('/confirmation/{orderNumber}', name: 'app_order_confirmation', methods: ['GET'])]
    public function confirmation(string $orderNumber): Response
    {
        $order = $this->orderService->findUserOrder($this->user(), $orderNumber);

        if (!$order) {
            throw $this->createNotFoundException('Commande non trouvée');
        }

        return $this->render('order/confirmation.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/mes-commandes', name: 'app_order_list', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('order/list.html.twig', [
            'orders' => $this->orderService->getUserOrders($this->user()),
        ]);
    }

    #[Route('/{id}/annuler', name: 'app_order_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(int $id, Request $request): Response
    {
        $order = $this->userOrder($id);

        if (!$this->isCsrfTokenValid('order_cancel_' . $order->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide');
        }

        // Le client n'annule lui-même qu'une commande qu'il n'a pas encore payée.
        if (!$order->isAwaitingPayment()) {
            $this->addFlash('error', 'Cette commande ne peut plus être annulée en ligne.');

            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        $this->orderService->cancelOrder($order);
        $this->addFlash('success', 'Votre commande est annulée. Rien n\'a été débité.');

        return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
    }

    #[Route('/{id}', name: 'app_order_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        return $this->render('order/show.html.twig', [
            'order' => $this->userOrder($id),
        ]);
    }

    private function user(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }

    private function userOrder(int $id): Order
    {
        $order = $this->orderRepository->findOneBy(['id' => $id, 'user' => $this->user()]);

        if (!$order) {
            throw $this->createNotFoundException('Commande non trouvée');
        }

        return $order;
    }

    private function defaultAddress(User $user): ?Address
    {
        $first = null;
        foreach ($user->getAddresses() as $address) {
            if ($address->isIsDefault()) {
                return $address;
            }
            $first ??= $address;
        }

        return $first;
    }
}
