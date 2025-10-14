<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Product;
use App\Form\CategoryType;
use App\Form\ProductType;
use App\Repository\CategoryRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Service\OrderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('/', name: 'app_admin_dashboard')]
    public function dashboard(
        ProductRepository $productRepository,
        OrderRepository $orderRepository,
        UserRepository $userRepository
    ): Response {
        $totalProducts = count($productRepository->findAll());
        $totalOrders = count($orderRepository->findAll());
        $totalUsers = count($userRepository->findAll());
        $recentOrders = $orderRepository->findRecentOrders(10);

        return $this->render('admin/dashboard.html.twig', [
            'total_products' => $totalProducts,
            'total_orders' => $totalOrders,
            'total_users' => $totalUsers,
            'recent_orders' => $recentOrders,
        ]);
    }

    // === GESTION DES PRODUITS ===

    #[Route('/produits', name: 'app_admin_products')]
    public function products(ProductRepository $productRepository): Response
    {
        $products = $productRepository->findAll();

        return $this->render('admin/products/index.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route('/produit/ajouter', name: 'app_admin_product_add')]
    public function addProduct(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $slug = $slugger->slug($product->getName())->lower();
            $product->setSlug($slug);

            $entityManager->persist($product);
            $entityManager->flush();

            $this->addFlash('success', 'Produit ajouté avec succès !');
            return $this->redirectToRoute('app_admin_products');
        }

        return $this->render('admin/products/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Ajouter un produit',
        ]);
    }

    #[Route('/produit/modifier/{id}', name: 'app_admin_product_edit')]
    public function editProduct(
        Product $product,
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $slug = $slugger->slug($product->getName())->lower();
            $product->setSlug($slug);
            $product->setUpdatedAt(new \DateTimeImmutable());

            $entityManager->flush();

            $this->addFlash('success', 'Produit modifié avec succès !');
            return $this->redirectToRoute('app_admin_products');
        }

        return $this->render('admin/products/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Modifier le produit',
            'product' => $product,
        ]);
    }

    #[Route('/produit/supprimer/{id}', name: 'app_admin_product_delete', methods: ['POST'])]
    public function deleteProduct(Product $product, Request $request, EntityManagerInterface $entityManager): Response
    {
        // Vérification CSRF
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_product_' . $product->getId(), $token)) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }

        $entityManager->remove($product);
        $entityManager->flush();

        $this->addFlash('success', 'Produit supprimé avec succès !');
        return $this->redirectToRoute('app_admin_products');
    }

    // === GESTION DES CATÉGORIES ===

    #[Route('/categories', name: 'app_admin_categories')]
    public function categories(CategoryRepository $categoryRepository): Response
    {
        $categories = $categoryRepository->findAll();

        return $this->render('admin/categories/index.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/categorie/ajouter', name: 'app_admin_category_add')]
    public function addCategory(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $slug = $slugger->slug($category->getName())->lower();
            $category->setSlug($slug);

            $entityManager->persist($category);
            $entityManager->flush();

            $this->addFlash('success', 'Catégorie ajoutée avec succès !');
            return $this->redirectToRoute('app_admin_categories');
        }

        return $this->render('admin/categories/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Ajouter une catégorie',
        ]);
    }

    #[Route('/categorie/modifier/{id}', name: 'app_admin_category_edit')]
    public function editCategory(
        Category $category,
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $slug = $slugger->slug($category->getName())->lower();
            $category->setSlug($slug);

            $entityManager->flush();

            $this->addFlash('success', 'Catégorie modifiée avec succès !');
            return $this->redirectToRoute('app_admin_categories');
        }

        return $this->render('admin/categories/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Modifier la catégorie',
        ]);
    }

    #[Route('/categorie/supprimer/{id}', name: 'app_admin_category_delete', methods: ['POST'])]
    public function deleteCategory(Category $category, Request $request, EntityManagerInterface $entityManager): Response
    {
        // Vérification CSRF
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_category_' . $category->getId(), $token)) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }

        $entityManager->remove($category);
        $entityManager->flush();

        $this->addFlash('success', 'Catégorie supprimée avec succès !');
        return $this->redirectToRoute('app_admin_categories');
    }

    // === GESTION DES COMMANDES ===

    #[Route('/commandes', name: 'app_admin_orders')]
    public function orders(OrderRepository $orderRepository): Response
    {
        $orders = $orderRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->render('admin/orders/index.html.twig', [
            'orders' => $orders,
        ]);
    }

    #[Route('/commande/{id}', name: 'app_admin_order_show')]
    public function showOrder(int $id, OrderRepository $orderRepository): Response
    {
        $order = $orderRepository->find($id);

        if (!$order) {
            throw $this->createNotFoundException('Commande non trouvée');
        }

        return $this->render('admin/orders/show.html.twig', [
            'order' => $order,
            'available_statuses' => \App\Entity\Order::getAvailableStatuses(),
        ]);
    }

    #[Route('/commande/{id}/statut', name: 'app_admin_order_status', methods: ['POST'])]
    public function updateOrderStatus(
        int $id,
        Request $request,
        OrderRepository $orderRepository,
        OrderService $orderService
    ): Response {
        $order = $orderRepository->find($id);

        if (!$order) {
            throw $this->createNotFoundException('Commande non trouvée');
        }

        // Vérification CSRF
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('update_order_status_' . $id, $token)) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }

        // Vérifier si la commande est payée avant de modifier le statut
        if (!$order->isPaid() && $order->getStatus() === \App\Entity\Order::STATUS_PENDING) {
            $this->addFlash('error', 'Cette commande ne peut pas être traitée car elle n\'a pas été payée.');
            return $this->redirectToRoute('app_admin_order_show', ['id' => $id]);
        }

        $status = $request->request->get('status');

        // Empêcher le passage à processing, shipped ou delivered si non payé
        $restrictedStatuses = [
            \App\Entity\Order::STATUS_PROCESSING,
            \App\Entity\Order::STATUS_SHIPPED,
            \App\Entity\Order::STATUS_DELIVERED
        ];

        if (in_array($status, $restrictedStatuses) && !$order->isPaid()) {
            $this->addFlash('error', 'Cette commande doit être payée avant d\'être traitée.');
            return $this->redirectToRoute('app_admin_order_show', ['id' => $id]);
        }

        try {
            $orderService->updateOrderStatus($order, $status);
            $this->addFlash('success', 'Statut de la commande mis à jour !');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_order_show', ['id' => $id]);
    }
}

