<?php

namespace App\Controller;

use App\Entity\Address;
use App\Form\AddressType;
use App\Form\OrderType;
use App\Service\CartService;
use App\Service\OrderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/commande')]
#[IsGranted('ROLE_USER')]
class OrderController extends AbstractController
{
    private OrderService $orderService;
    private CartService $cartService;

    public function __construct(OrderService $orderService, CartService $cartService)
    {
        $this->orderService = $orderService;
        $this->cartService = $cartService;
    }

    #[Route('/creer', name: 'app_order_create')]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $cart = $this->cartService->getCart($user);

        if ($cart->getItems()->isEmpty()) {
            $this->addFlash('error', 'Votre panier est vide');
            return $this->redirectToRoute('app_cart_index');
        }

        // Adresse par défaut ou nouvelle adresse
        $address = $user->getAddresses()->first() ?: new Address();
        
        $form = $this->createForm(OrderType::class, [
            'address' => $address,
            'notes' => null,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $addressData = $data['address'];
            $notes = $data['notes'];

            // Sauvegarder l'adresse si c'est une nouvelle
            if (!$addressData->getId()) {
                $addressData->setUser($user);
                $entityManager->persist($addressData);
                $entityManager->flush();
            }

            try {
                $order = $this->orderService->createOrderFromCart($cart, $addressData, $notes);
                $this->addFlash('success', 'Votre commande a été créée avec succès !');
                return $this->redirectToRoute('app_order_confirmation', ['orderNumber' => $order->getOrderNumber()]);
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('order/create.html.twig', [
            'cart' => $cart,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/confirmation/{orderNumber}', name: 'app_order_confirmation')]
    public function confirmation(string $orderNumber): Response
    {
        $user = $this->getUser();
        $orders = $this->orderService->getUserOrders($user);

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

        return $this->render('order/confirmation.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/mes-commandes', name: 'app_order_list')]
    public function list(): Response
    {
        $user = $this->getUser();
        $orders = $this->orderService->getUserOrders($user);

        return $this->render('order/list.html.twig', [
            'orders' => $orders,
        ]);
    }

    #[Route('/{id}', name: 'app_order_show')]
    public function show(int $id): Response
    {
        $user = $this->getUser();
        $orders = $this->orderService->getUserOrders($user);

        $order = null;
        foreach ($orders as $o) {
            if ($o->getId() === $id) {
                $order = $o;
                break;
            }
        }

        if (!$order) {
            throw $this->createNotFoundException('Commande non trouvée');
        }

        return $this->render('order/show.html.twig', [
            'order' => $order,
        ]);
    }
}

