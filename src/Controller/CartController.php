<?php

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\User;
use App\Exception\ShopException;
use App\Repository\ProductRepository;
use App\Service\CartService;
use App\Util\Money;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/panier')]
#[IsGranted('ROLE_USER')]
class CartController extends AbstractController
{
    public function __construct(private readonly CartService $cartService)
    {
    }

    #[Route('/', name: 'app_cart_index', methods: ['GET'])]
    public function index(): Response
    {
        $cart = $this->cart();

        foreach ($this->cartService->refresh($cart) as $notice) {
            $this->addFlash('warning', $notice);
        }

        return $this->render('cart/index.html.twig', [
            'cart' => $cart,
            'max_quantity' => CartService::MAX_QUANTITY,
        ]);
    }

    #[Route('/ajouter/{id}', name: 'app_cart_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function add(int $id, Request $request, ProductRepository $productRepository): Response
    {
        $this->checkCsrf('cart_add_' . $id, $request);

        $product = $productRepository->find($id);

        if (!$product || !$product->isIsActive()) {
            throw $this->createNotFoundException('Produit non trouvé');
        }

        $cart = $this->cart();
        $wantsJson = $this->wantsJson($request);

        try {
            $this->cartService->addProduct($cart, $product, $request->request->getInt('quantity', 1));
        } catch (ShopException $e) {
            if ($wantsJson) {
                return new JsonResponse(['ok' => false, 'message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $this->addFlash('error', $e->getMessage());

            return $this->redirectBack($request);
        }

        if ($wantsJson) {
            return new JsonResponse([
                'ok' => true,
                'message' => 'Ajouté au panier',
                'count' => $cart->getTotalItems(),
                'total' => Money::format($cart->getTotalCents()),
                'item' => [
                    'name' => $product->getName(),
                    'price' => Money::format($product->getPriceCents()),
                    'image' => $product->getImageUrl(),
                ],
            ]);
        }

        $this->addFlash('success', sprintf('« %s » est dans votre panier.', $product->getName()));

        return $this->redirectBack($request);
    }

    #[Route('/modifier/{id}', name: 'app_cart_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(int $id, Request $request): Response
    {
        $this->checkCsrf('cart_update_' . $id, $request);

        $cart = $this->cart();
        $cartItem = $this->findItem($cart, $id);

        try {
            $this->cartService->updateQuantity($cartItem, $request->request->getInt('quantity', 1));
        } catch (ShopException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/supprimer/{id}', name: 'app_cart_remove', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function remove(int $id, Request $request): Response
    {
        $this->checkCsrf('cart_remove_' . $id, $request);

        $cartItem = $this->findItem($this->cart(), $id);
        $name = $cartItem->getProduct()->getName();

        $this->cartService->removeItem($cartItem);
        $this->addFlash('success', sprintf('« %s » a été retiré du panier.', $name));

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/vider', name: 'app_cart_clear', methods: ['POST'])]
    public function clear(Request $request): Response
    {
        $this->checkCsrf('cart_clear', $request);

        $this->cartService->clearCart($this->cart());
        $this->addFlash('success', 'Votre panier est vide.');

        return $this->redirectToRoute('app_cart_index');
    }

    private function cart(): Cart
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $this->cartService->getCart($user);
    }

    private function findItem(Cart $cart, int $id): CartItem
    {
        foreach ($cart->getItems() as $item) {
            if ($item->getId() === $id) {
                return $item;
            }
        }

        throw $this->createNotFoundException('Article non trouvé dans le panier');
    }

    private function checkCsrf(string $id, Request $request): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide');
        }
    }

    private function wantsJson(Request $request): bool
    {
        return 'fetch' === $request->headers->get('X-Requested-With');
    }

    /**
     * Retour à la page d'origine, uniquement si elle appartient à ce site.
     */
    private function redirectBack(Request $request): Response
    {
        $referer = (string) $request->headers->get('referer');
        $parts = '' !== $referer ? parse_url($referer) : false;

        if (\is_array($parts)
            && ($parts['host'] ?? null) === $request->getHost()
            && str_starts_with($parts['path'] ?? '', '/')
            && !str_starts_with($parts['path'], '//')
        ) {
            return $this->redirect($parts['path'] . (isset($parts['query']) ? '?' . $parts['query'] : ''));
        }

        return $this->redirectToRoute('app_cart_index');
    }
}
