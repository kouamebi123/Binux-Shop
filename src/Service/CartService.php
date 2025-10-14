<?php

namespace App\Service;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CartRepository;
use App\Repository\CartItemRepository;
use Doctrine\ORM\EntityManagerInterface;

class CartService
{
    private EntityManagerInterface $entityManager;
    private CartRepository $cartRepository;
    private CartItemRepository $cartItemRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        CartRepository $cartRepository,
        CartItemRepository $cartItemRepository
    ) {
        $this->entityManager = $entityManager;
        $this->cartRepository = $cartRepository;
        $this->cartItemRepository = $cartItemRepository;
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

    public function addProduct(Cart $cart, Product $product, int $quantity = 1): void
    {
        // Vérifier si le produit est déjà dans le panier
        $cartItem = null;
        foreach ($cart->getItems() as $item) {
            if ($item->getProduct()->getId() === $product->getId()) {
                $cartItem = $item;
                break;
            }
        }

        if ($cartItem) {
            // Augmenter la quantité
            $newQuantity = $cartItem->getQuantity() + $quantity;
            
            // Vérifier le stock
            if ($newQuantity > $product->getStock()) {
                throw new \Exception('Stock insuffisant pour ce produit.');
            }
            
            $cartItem->setQuantity($newQuantity);
        } else {
            // Vérifier le stock
            if ($quantity > $product->getStock()) {
                throw new \Exception('Stock insuffisant pour ce produit.');
            }

            // Créer un nouvel item
            $cartItem = new CartItem();
            $cartItem->setCart($cart);
            $cartItem->setProduct($product);
            $cartItem->setQuantity($quantity);
            $cartItem->setPrice($product->getPrice());
            
            $cart->addItem($cartItem);
            $this->entityManager->persist($cartItem);
        }

        $cart->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    public function updateQuantity(CartItem $cartItem, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->removeItem($cartItem);
            return;
        }

        // Vérifier le stock
        if ($quantity > $cartItem->getProduct()->getStock()) {
            throw new \Exception('Stock insuffisant pour ce produit.');
        }

        $cartItem->setQuantity($quantity);
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
}

