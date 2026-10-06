<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Order;
use App\Entity\Product;
use App\Exception\ShopException;
use App\Form\CategoryType;
use App\Form\ProductType;
use App\Repository\CategoryRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Service\OrderService;
use App\Service\SlugGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('/', name: 'app_admin_dashboard', methods: ['GET'])]
    public function dashboard(
        ProductRepository $productRepository,
        OrderRepository $orderRepository,
        UserRepository $userRepository,
        OrderService $orderService,
    ): Response {
        // Les commandes abandonnées rendent leur stock avant l'affichage des chiffres.
        $orderService->expireUnpaidOrders();

        $figures = $orderRepository->dashboardFigures();

        return $this->render('admin/dashboard.html.twig', [
            'figures' => $figures,
            'average_cents' => $figures['paid_orders'] > 0 ? intdiv($figures['revenue_cents'], $figures['paid_orders']) : 0,
            'revenue_by_day' => $orderRepository->revenueByDay(14),
            'total_products' => $productRepository->count([]),
            'total_orders' => $figures['total'],
            'total_users' => $userRepository->count([]),
            'recent_orders' => $orderRepository->findForAdmin(null, 8),
            'low_stock' => $productRepository->findLowStock(5, 6),
        ]);
    }

    // === GESTION DES PRODUITS ===

    #[Route('/produits', name: 'app_admin_products', methods: ['GET'])]
    public function products(ProductRepository $productRepository): Response
    {
        return $this->render('admin/products/index.html.twig', [
            'products' => $productRepository->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC']),
        ]);
    }

    #[Route('/produit/ajouter', name: 'app_admin_product_add', methods: ['GET', 'POST'])]
    public function addProduct(Request $request, EntityManagerInterface $entityManager, SlugGenerator $slugs): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $product->setSlug($slugs->forProduct($product));

            $entityManager->persist($product);
            $entityManager->flush();

            $this->addFlash('success', 'Produit ajouté avec succès !');

            return $this->redirectToRoute('app_admin_products');
        }

        return $this->render('admin/products/form.html.twig', [
            'form' => $form,
            'title' => 'Ajouter un produit',
        ]);
    }

    #[Route('/produit/modifier/{id}', name: 'app_admin_product_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editProduct(Product $product, Request $request, EntityManagerInterface $entityManager, SlugGenerator $slugs): Response
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $product->setSlug($slugs->forProduct($product));
            $product->setUpdatedAt(new \DateTimeImmutable());

            $entityManager->flush();

            $this->addFlash('success', 'Produit modifié avec succès !');

            return $this->redirectToRoute('app_admin_products');
        }

        return $this->render('admin/products/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier le produit',
            'product' => $product,
        ]);
    }

    #[Route('/produit/supprimer/{id}', name: 'app_admin_product_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteProduct(Product $product, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->checkCsrf('delete_product_' . $product->getId(), $request);

        // Un produit déjà commandé reste dans l'historique : il est retiré de la vente, pas effacé.
        if (!$product->getOrderItems()->isEmpty()) {
            $product->setIsActive(false);
            $product->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('warning', sprintf('« %s » figure dans des commandes : il a été retiré de la vente plutôt que supprimé.', $product->getName()));

            return $this->redirectToRoute('app_admin_products');
        }

        $entityManager->remove($product);
        $entityManager->flush();

        $this->addFlash('success', 'Produit supprimé avec succès !');

        return $this->redirectToRoute('app_admin_products');
    }

    // === GESTION DES CATÉGORIES ===

    #[Route('/categories', name: 'app_admin_categories', methods: ['GET'])]
    public function categories(CategoryRepository $categoryRepository): Response
    {
        return $this->render('admin/categories/index.html.twig', [
            'categories' => $categoryRepository->findAllOrdered(),
        ]);
    }

    #[Route('/categorie/ajouter', name: 'app_admin_category_add', methods: ['GET', 'POST'])]
    public function addCategory(Request $request, EntityManagerInterface $entityManager, SlugGenerator $slugs): Response
    {
        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $category->setSlug($slugs->forCategory($category));

            $entityManager->persist($category);
            $entityManager->flush();

            $this->addFlash('success', 'Catégorie ajoutée avec succès !');

            return $this->redirectToRoute('app_admin_categories');
        }

        return $this->render('admin/categories/form.html.twig', [
            'form' => $form,
            'title' => 'Ajouter une catégorie',
        ]);
    }

    #[Route('/categorie/modifier/{id}', name: 'app_admin_category_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editCategory(Category $category, Request $request, EntityManagerInterface $entityManager, SlugGenerator $slugs): Response
    {
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $category->setSlug($slugs->forCategory($category));

            $entityManager->flush();

            $this->addFlash('success', 'Catégorie modifiée avec succès !');

            return $this->redirectToRoute('app_admin_categories');
        }

        return $this->render('admin/categories/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier la catégorie',
            'category' => $category,
        ]);
    }

    #[Route('/categorie/supprimer/{id}', name: 'app_admin_category_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteCategory(Category $category, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->checkCsrf('delete_category_' . $category->getId(), $request);

        if (!$category->getProducts()->isEmpty()) {
            $this->addFlash('error', sprintf('« %s » contient encore %d produit(s). Déplacez-les ou supprimez-les d\'abord.', $category->getName(), $category->getProducts()->count()));

            return $this->redirectToRoute('app_admin_categories');
        }

        $entityManager->remove($category);
        $entityManager->flush();

        $this->addFlash('success', 'Catégorie supprimée avec succès !');

        return $this->redirectToRoute('app_admin_categories');
    }

    // === GESTION DES COMMANDES ===

    #[Route('/commandes', name: 'app_admin_orders', methods: ['GET'])]
    public function orders(Request $request, OrderRepository $orderRepository): Response
    {
        $status = (string) $request->query->get('statut', '');
        if (!\array_key_exists($status, Order::getAvailableStatuses())) {
            $status = null;
        }

        return $this->render('admin/orders/index.html.twig', [
            'orders' => $orderRepository->findForAdmin($status),
            'current_status' => $status,
            'available_statuses' => Order::getAvailableStatuses(),
        ]);
    }

    #[Route('/commande/{id}', name: 'app_admin_order_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showOrder(int $id, OrderRepository $orderRepository, OrderService $orderService): Response
    {
        $order = $orderRepository->find($id);

        if (!$order) {
            throw $this->createNotFoundException('Commande non trouvée');
        }

        return $this->render('admin/orders/show.html.twig', [
            'order' => $order,
            'available_statuses' => Order::getAvailableStatuses(),
            'allowed_statuses' => $orderService->allowedTransitions($order),
        ]);
    }

    #[Route('/commande/{id}/statut', name: 'app_admin_order_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updateOrderStatus(int $id, Request $request, OrderRepository $orderRepository, OrderService $orderService): Response
    {
        $order = $orderRepository->find($id);

        if (!$order) {
            throw $this->createNotFoundException('Commande non trouvée');
        }

        $this->checkCsrf('update_order_status_' . $id, $request);

        try {
            $orderService->updateOrderStatus($order, (string) $request->request->get('status'));
            $this->addFlash('success', 'Statut de la commande mis à jour !');
        } catch (ShopException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_order_show', ['id' => $id]);
    }

    private function checkCsrf(string $id, Request $request): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide');
        }
    }
}
