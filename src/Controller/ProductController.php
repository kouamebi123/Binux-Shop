<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/produits')]
class ProductController extends AbstractController
{
    #[Route('/', name: 'app_product_index')]
    public function index(ProductRepository $productRepository, CategoryRepository $categoryRepository): Response
    {
        $products = $productRepository->findActiveProducts();
        $categories = $categoryRepository->findAllOrdered();

        return $this->render('product/index.html.twig', [
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    #[Route('/categorie/{slug}', name: 'app_product_category')]
    public function category(
        string $slug,
        CategoryRepository $categoryRepository,
        ProductRepository $productRepository
    ): Response {
        $category = $categoryRepository->findOneBy(['slug' => $slug]);

        if (!$category) {
            throw $this->createNotFoundException('Catégorie non trouvée');
        }

        $products = $productRepository->findByCategory($category);
        $categories = $categoryRepository->findAllOrdered();

        return $this->render('product/category.html.twig', [
            'category' => $category,
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    #[Route('/recherche', name: 'app_product_search')]
    public function search(Request $request, ProductRepository $productRepository): Response
    {
        $query = $request->query->get('q', '');
        $products = [];

        if ($query) {
            $products = $productRepository->searchProducts($query);
        }

        return $this->render('product/search.html.twig', [
            'query' => $query,
            'products' => $products,
        ]);
    }

    #[Route('/{slug}', name: 'app_product_show')]
    public function show(string $slug, ProductRepository $productRepository): Response
    {
        $product = $productRepository->findOneBy(['slug' => $slug, 'isActive' => true]);

        if (!$product) {
            throw $this->createNotFoundException('Produit non trouvé');
        }

        // Produits similaires de la même catégorie
        $relatedProducts = $productRepository->findByCategory($product->getCategory());
        $relatedProducts = array_filter($relatedProducts, function ($p) use ($product) {
            return $p->getId() !== $product->getId();
        });
        $relatedProducts = array_slice($relatedProducts, 0, 4);

        return $this->render('product/show.html.twig', [
            'product' => $product,
            'related_products' => $relatedProducts,
        ]);
    }
}

