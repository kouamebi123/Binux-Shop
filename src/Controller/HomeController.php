<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(ProductRepository $productRepository, CategoryRepository $categoryRepository): Response
    {
        $featuredProducts = $productRepository->findFeaturedProducts(8);
        $latestProducts = $productRepository->findLatestProducts(12);
        $categories = $categoryRepository->findAllOrdered();

        return $this->render('home/index.html.twig', [
            'featured_products' => $featuredProducts,
            'latest_products' => $latestProducts,
            'categories' => $categories,
        ]);
    }
}

