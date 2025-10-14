<?php

namespace App\Service;

use App\Entity\Cart;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Entity\Address;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;

class OrderService
{
    private EntityManagerInterface $entityManager;
    private OrderRepository $orderRepository;
    private CartService $cartService;

    public function __construct(
        EntityManagerInterface $entityManager,
        OrderRepository $orderRepository,
        CartService $cartService
    ) {
        $this->entityManager = $entityManager;
        $this->orderRepository = $orderRepository;
        $this->cartService = $cartService;
    }

    public function createOrderFromCart(Cart $cart, Address $address, ?string $notes = null): Order
    {
        if ($cart->getItems()->isEmpty()) {
            throw new \Exception('Votre panier est vide.');
        }

        // Vérifier le stock de tous les produits
        foreach ($cart->getItems() as $cartItem) {
            if ($cartItem->getQuantity() > $cartItem->getProduct()->getStock()) {
                throw new \Exception(
                    sprintf(
                        'Stock insuffisant pour le produit "%s". Stock disponible: %d',
                        $cartItem->getProduct()->getName(),
                        $cartItem->getProduct()->getStock()
                    )
                );
            }
        }

        // Créer la commande
        $order = new Order();
        $order->setUser($cart->getUser());
        $order->setShippingAddress($address->getFullAddress());
        $order->setTotal((string)$cart->getTotal());
        $order->setNotes($notes);

        // Créer les items de commande et réduire le stock
        foreach ($cart->getItems() as $cartItem) {
            $orderItem = new OrderItem();
            $orderItem->setOrderRef($order);
            $orderItem->setProduct($cartItem->getProduct());
            $orderItem->setProductName($cartItem->getProduct()->getName());
            $orderItem->setQuantity($cartItem->getQuantity());
            $orderItem->setPrice($cartItem->getPrice());

            $order->addItem($orderItem);

            // Réduire le stock
            $product = $cartItem->getProduct();
            $product->setStock($product->getStock() - $cartItem->getQuantity());
            $product->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($orderItem);
        }

        $this->entityManager->persist($order);
        
        // Vider le panier
        $this->cartService->clearCart($cart);

        $this->entityManager->flush();

        return $order;
    }

    public function updateOrderStatus(Order $order, string $status): void
    {
        $availableStatuses = array_keys(Order::getAvailableStatuses());
        
        if (!in_array($status, $availableStatuses)) {
            throw new \Exception('Statut invalide.');
        }

        $order->setStatus($status);
        $this->entityManager->flush();
    }

    public function getUserOrders(User $user): array
    {
        return $this->orderRepository->findByUser($user);
    }

    public function cancelOrder(Order $order): void
    {
        if ($order->getStatus() === Order::STATUS_DELIVERED || $order->getStatus() === Order::STATUS_CANCELLED) {
            throw new \Exception('Cette commande ne peut pas être annulée.');
        }

        // Remettre les produits en stock
        foreach ($order->getItems() as $orderItem) {
            $product = $orderItem->getProduct();
            $product->setStock($product->getStock() + $orderItem->getQuantity());
            $product->setUpdatedAt(new \DateTimeImmutable());
        }

        $order->setStatus(Order::STATUS_CANCELLED);
        $this->entityManager->flush();
    }
}

