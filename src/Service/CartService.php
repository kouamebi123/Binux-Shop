<?php

namespace App\Service;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;
use App\Exception\ShopException;
use App\Repository\CartRepository;
use Doctrine\ORM\EntityManagerInterface;

class CartService
{
    /** Quantité maximale d'un même article par panier. */
    public const MAX_QUANTITY = 20;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CartRepository $cartRepository,
    ) {
    }

    public function getCart(User $user): Cart
    {
        $cart = $this->cartRepository->findByUser($user);

        if (!$cart) {
            $cart = new Cart();
            $cart->setUser($user);
            $this->entityManager->persist($cart);
            $this->entityManager->flush();
        }

        return $cart;
    }

    /**
     * Remet le panier d'accord avec le catalogue : prix du jour, articles retirés de la vente,
     * quantités ramenées au stock disponible. Renvoie les messages à montrer au client.
     *
     * @return string[]
     */
    public function refresh(Cart $cart): array
    {
        $notices = [];
        $changed = false;

        foreach ($cart->getItems()->toArray() as $item) {
            $product = $item->getProduct();

            if (!$product->isIsActive() || $product->getStock() < 1) {
                $notices[] = sprintf('« %s » n\'est plus disponible et a été retiré de votre panier.', $product->getName());
                $cart->removeItem($item);
                $this->entityManager->remove($item);
                $changed = true;
                continue;
            }

            if ($item->getQuantity() > $product->getStock()) {
                $notices[] = sprintf('Il ne reste que %d exemplaire(s) de « %s » : la quantité a été ajustée.', $product->getStock(), $product->getName());
                $item->setQuantity($product->getStock());
                $changed = true;
            }

            if ($item->getPrice() !== $product->getPrice()) {
                $notices[] = sprintf('Le prix de « %s » a changé depuis son ajout au panier.', $product->getName());
                $item->setPrice($product->getPrice());
                $changed = true;
            }
        }

        if ($changed) {
            $cart->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
        }

        return $notices;
    }

    public function addProduct(Cart $cart, Product $product, int $quantity = 1): CartItem
    {
        if ($quantity < 1) {
            throw new ShopException('Choisissez une quantité d\'au moins 1.');
        }

        if (!$product->isIsActive()) {
            throw new ShopException('Cet article n\'est plus en vente.');
        }

        $cartItem = null;
        foreach ($cart->getItems() as $item) {
            if ($item->getProduct()->getId() === $product->getId()) {
                $cartItem = $item;
                break;
            }
        }

        $newQuantity = ($cartItem?->getQuantity() ?? 0) + $quantity;
        $this->assertQuantity($product, $newQuantity);

        if (!$cartItem) {
            $cartItem = new CartItem();
            $cartItem->setCart($cart);
            $cartItem->setProduct($product);
            $cart->addItem($cartItem);
            $this->entityManager->persist($cartItem);
        }

        $cartItem->setQuantity($newQuantity);
        $cartItem->setPrice($product->getPrice());

        $cart->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $cartItem;
    }

    public function updateQuantity(CartItem $cartItem, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->removeItem($cartItem);

            return;
        }

        $this->assertQuantity($cartItem->getProduct(), $quantity);

        $cartItem->setQuantity($quantity);
        $cartItem->setPrice($cartItem->getProduct()->getPrice());
        $cartItem->getCart()->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    public function removeItem(CartItem $cartItem): void
    {
        $cart = $cartItem->getCart();
        $cart->removeItem($cartItem);
        $this->entityManager->remove($cartItem);
        $this->entityManager->flush();
    }

    public function clearCart(Cart $cart): void
    {
        foreach ($cart->getItems() as $item) {
            $this->entityManager->remove($item);
        }

        $cart->clear();
        $this->entityManager->flush();
    }

    private function assertQuantity(Product $product, int $quantity): void
    {
        if ($quantity > self::MAX_QUANTITY) {
            throw new ShopException(sprintf('Vous pouvez commander au plus %d exemplaires d\'un même article.', self::MAX_QUANTITY));
        }

        if ($quantity > $product->getStock()) {
            throw new ShopException(
                $product->getStock() > 0
                    ? sprintf('Il ne reste que %d exemplaire(s) de « %s ».', $product->getStock(), $product->getName())
                    : sprintf('« %s » est épuisé.', $product->getName())
            );
        }
    }
}
