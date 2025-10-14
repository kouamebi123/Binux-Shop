<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/panier')]
#[IsGranted('ROLE_USER')]
class CartController extends AbstractController
{
    private CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    #[Route('/', name: 'app_cart_index')]
    public function index(): Response
    {
        $user = $this->getUser();
        $cart = $this->cartService->getCart($user);

        return $this->render('cart/index.html.twig', [
            'cart' => $cart,
        ]);
    }

    #[Route('/ajouter/{id}', name: 'app_cart_add', methods: ['POST'])]
    public function add(
        int $id,
        Request $request,
        ProductRepository $productRepository
    ): Response {
        // Vérification CSRF
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('cart_add_' . $id, $token)) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }

        $product = $productRepository->find($id);

        if (!$product || !$product->isIsActive()) {
            throw $this->createNotFoundException('Produit non trouvé');
        }

        $user = $this->getUser();
        $cart = $this->cartService->getCart($user);

        $quantity = (int) $request->request->get('quantity', 1);

        try {
            $this->cartService->addProduct($cart, $product, $quantity);
            $this->addFlash('success', 'Produit ajouté au panier !');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        // Rediriger vers la page précédente ou le panier
        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/modifier/{id}', name: 'app_cart_update', methods: ['POST'])]
    public function update(int $id, Request $request): Response
    {
        // Vérification CSRF
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('cart_update_' . $id, $token)) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }

        $user = $this->getUser();
        $cart = $this->cartService->getCart($user);

        $cartItem = null;
        foreach ($cart->getItems() as $item) {
            if ($item->getId() === $id) {
                $cartItem = $item;
                break;
            }
        }

        if (!$cartItem) {
            throw $this->createNotFoundException('Article non trouvé dans le panier');
        }

        $quantity = (int) $request->request->get('quantity', 1);

        try {
            $this->cartService->updateQuantity($cartItem, $quantity);
            $this->addFlash('success', 'Panier mis à jour !');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/supprimer/{id}', name: 'app_cart_remove', methods: ['POST'])]
    public function remove(int $id, Request $request): Response
    {
        // Vérification CSRF
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('cart_remove_' . $id, $token)) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }

        $user = $this->getUser();
        $cart = $this->cartService->getCart($user);

        $cartItem = null;
        foreach ($cart->getItems() as $item) {
            if ($item->getId() === $id) {
                $cartItem = $item;
                break;
            }
        }

        if (!$cartItem) {
            throw $this->createNotFoundException('Article non trouvé dans le panier');
        }

        $this->cartService->removeItem($cartItem);
        $this->addFlash('success', 'Article retiré du panier');

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/vider', name: 'app_cart_clear', methods: ['POST'])]
    public function clear(Request $request): Response
    {
        // Vérification CSRF
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('cart_clear', $token)) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }

        $user = $this->getUser();
        $cart = $this->cartService->getCart($user);

        $this->cartService->clearCart($cart);
        $this->addFlash('success', 'Panier vidé');

        return $this->redirectToRoute('app_cart_index');
    }
}

