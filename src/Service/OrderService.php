<?php

namespace App\Service;

use App\Entity\Address;
use App\Entity\Cart;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Exception\ShopException;
use App\Repository\OrderRepository;
use App\Util\Money;
use Doctrine\ORM\EntityManagerInterface;

class OrderService
{
    /** Délai au-delà duquel une commande jamais payée est annulée et son stock libéré. */
    public const UNPAID_LIFETIME = 'PT2H';

    /** Enchaînements de statuts autorisés depuis l'administration. */
    private const TRANSITIONS = [
        Order::STATUS_PENDING => [Order::STATUS_PROCESSING, Order::STATUS_CANCELLED],
        Order::STATUS_PROCESSING => [Order::STATUS_SHIPPED, Order::STATUS_CANCELLED],
        Order::STATUS_SHIPPED => [Order::STATUS_DELIVERED, Order::STATUS_CANCELLED],
        Order::STATUS_DELIVERED => [],
        Order::STATUS_CANCELLED => [],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderRepository $orderRepository,
        private readonly CartService $cartService,
    ) {
    }

    public function createOrderFromCart(Cart $cart, Address $address, ?string $notes = null): Order
    {
        if ($cart->getItems()->isEmpty()) {
            throw new ShopException('Votre panier est vide.');
        }

        // Les commandes abandonnées sans paiement rendent d'abord leur stock.
        $this->expireUnpaidOrders();

        // Premier contrôle, pour un message clair sans ouvrir de transaction.
        foreach ($cart->getItems() as $cartItem) {
            $product = $cartItem->getProduct();
            $this->entityManager->refresh($product);

            if (!$product->isIsActive() || $cartItem->getQuantity() > $product->getStock()) {
                throw new ShopException(sprintf('« %s » n\'est plus disponible dans la quantité demandée.', $product->getName()));
            }
        }

        return $this->entityManager->wrapInTransaction(function () use ($cart, $address, $notes): Order {
            $order = new Order();
            $order->setUser($cart->getUser());
            $order->setShippingAddress($address->getFullAddress());
            $order->setNotes($notes);

            $totalCents = 0;

            foreach ($cart->getItems() as $cartItem) {
                $product = $cartItem->getProduct();
                $quantity = (int) $cartItem->getQuantity();

                if ($quantity < 1 || $quantity > CartService::MAX_QUANTITY) {
                    throw new ShopException(sprintf('La quantité demandée pour « %s » n\'est pas valide.', $product->getName()));
                }

                // Réservation atomique : la ligne n'est modifiée que si le stock suffit encore,
                // ce qui empêche deux commandes simultanées de vendre le même exemplaire.
                $reserved = $this->entityManager->getConnection()->executeStatement(
                    'UPDATE product SET stock = stock - :quantity, updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id AND is_active = true AND stock >= :quantity',
                    ['quantity' => $quantity, 'id' => $product->getId()]
                );

                if (1 !== $reserved) {
                    throw new ShopException(sprintf('« %s » n\'est plus disponible dans la quantité demandée.', $product->getName()));
                }

                $this->entityManager->refresh($product);

                $orderItem = new OrderItem();
                $orderItem->setProduct($product);
                $orderItem->setProductName($product->getName());
                $orderItem->setQuantity($quantity);
                // Prix du catalogue au moment de la commande, jamais celui mémorisé dans le panier.
                $orderItem->setPrice($product->getPrice());
                $order->addItem($orderItem);
                $this->entityManager->persist($orderItem);

                $totalCents += $orderItem->getTotalCents();
            }

            $order->setTotal(Money::toDecimal($totalCents));
            $this->entityManager->persist($order);

            $this->cartService->clearCart($cart);
            $this->entityManager->flush();

            return $order;
        });
    }

    /**
     * @return string[] statuts vers lesquels la commande peut passer
     */
    public function allowedTransitions(Order $order): array
    {
        $allowed = self::TRANSITIONS[$order->getStatus()] ?? [];

        // Rien ne s'expédie ni ne se prépare tant que le paiement n'est pas encaissé.
        if (!$order->isPaid()) {
            $allowed = array_values(array_intersect($allowed, [Order::STATUS_CANCELLED]));
        }

        return $allowed;
    }

    public function updateOrderStatus(Order $order, string $status): void
    {
        if (!\array_key_exists($status, Order::getAvailableStatuses())) {
            throw new ShopException('Statut invalide.');
        }

        if ($status === $order->getStatus()) {
            return;
        }

        if (!\in_array($status, $this->allowedTransitions($order), true)) {
            throw new ShopException(
                $order->isPaid()
                    ? sprintf('Une commande « %s » ne peut pas passer à « %s ».', $order->getStatusLabel(), Order::getAvailableStatuses()[$status])
                    : 'Cette commande doit être payée avant d\'être traitée.'
            );
        }

        if (Order::STATUS_CANCELLED === $status) {
            $this->cancelOrder($order);

            return;
        }

        $order->setStatus($status);
        $this->entityManager->flush();
    }

    /**
     * @return Order[]
     */
    public function getUserOrders(User $user): array
    {
        return $this->orderRepository->findByUser($user);
    }

    public function findUserOrder(User $user, string $orderNumber): ?Order
    {
        return $this->orderRepository->findOneBy(['user' => $user, 'orderNumber' => $orderNumber]);
    }

    /**
     * Annule la commande et remet ses articles en stock.
     */
    public function cancelOrder(Order $order): void
    {
        if (\in_array($order->getStatus(), [Order::STATUS_DELIVERED, Order::STATUS_CANCELLED], true)) {
            throw new ShopException('Cette commande ne peut plus être annulée.');
        }

        $this->entityManager->wrapInTransaction(function () use ($order): void {
            foreach ($order->getItems() as $orderItem) {
                $this->entityManager->getConnection()->executeStatement(
                    'UPDATE product SET stock = stock + :quantity, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
                    ['quantity' => (int) $orderItem->getQuantity(), 'id' => $orderItem->getProduct()->getId()]
                );
                $this->entityManager->refresh($orderItem->getProduct());
            }

            $order->setStatus(Order::STATUS_CANCELLED);
            $this->entityManager->flush();
        });
    }

    /**
     * Annule les commandes restées sans paiement au-delà du délai et libère leur stock.
     */
    public function expireUnpaidOrders(?\DateTimeImmutable $now = null): int
    {
        $limit = ($now ?? new \DateTimeImmutable())->sub(new \DateInterval(self::UNPAID_LIFETIME));
        $expired = 0;

        foreach ($this->orderRepository->findUnpaidBefore($limit) as $order) {
            $this->cancelOrder($order);
            ++$expired;
        }

        return $expired;
    }

    /**
     * Une commande annulée faute de paiement vient finalement d'être réglée :
     * ses articles sont repris sur le stock, sans jamais le rendre négatif.
     */
    public function reviveCancelledOrder(Order $order): void
    {
        foreach ($order->getItems() as $orderItem) {
            $this->entityManager->getConnection()->executeStatement(
                'UPDATE product SET stock = GREATEST(stock - :quantity, 0), updated_at = CURRENT_TIMESTAMP WHERE id = :id',
                ['quantity' => (int) $orderItem->getQuantity(), 'id' => $orderItem->getProduct()->getId()]
            );
            $this->entityManager->refresh($orderItem->getProduct());
        }
    }
}
