<?php

namespace App\Controller;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Util\Money;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/produits')]
class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly CategoryRepository $categoryRepository,
    ) {
    }

    #[Route('/', name: 'app_product_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->listing($request, 'product/index.html.twig');
    }

    #[Route('/categorie/{slug}', name: 'app_product_category', methods: ['GET'])]
    public function category(string $slug, Request $request): Response
    {
        $category = $this->categoryRepository->findOneBy(['slug' => $slug]);

        if (!$category) {
            throw $this->createNotFoundException('Catégorie non trouvée');
        }

        return $this->listing($request, 'product/category.html.twig', $category);
    }

    #[Route('/recherche', name: 'app_product_search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        return $this->listing($request, 'product/search.html.twig');
    }

    #[Route('/suggestions', name: 'app_product_suggest', methods: ['GET'])]
    public function suggest(Request $request, RateLimiterFactory $searchLimiter): JsonResponse
    {
        if (!$searchLimiter->create((string) $request->getClientIp())->consume()->isAccepted()) {
            return new JsonResponse(['items' => []], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $query = trim((string) $request->query->get('q', ''));
        $items = [];

        if (mb_strlen($query) >= 2) {
            foreach ($this->productRepository->findForListing(query: $query, perPage: 6)['items'] as $product) {
                $items[] = [
                    'name' => $product->getName(),
                    'category' => $product->getCategory()->getName(),
                    'price' => Money::format($product->getPriceCents()),
                    'image' => $product->getImageUrl(),
                    'url' => $this->generateUrl('app_product_show', ['slug' => $product->getSlug()]),
                ];
            }
        }

        return new JsonResponse(['items' => $items]);
    }

    #[Route('/{slug}', name: 'app_product_show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $product = $this->productRepository->findOneBy(['slug' => $slug, 'isActive' => true]);

        if (!$product) {
            throw $this->createNotFoundException('Produit non trouvé');
        }

        return $this->render('product/show.html.twig', [
            'product' => $product,
            'related_products' => $this->productRepository->findRelated($product, 4),
            'max_quantity' => \App\Service\CartService::MAX_QUANTITY,
        ]);
    }

    private function listing(Request $request, string $template, ?Category $category = null): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('tri', 'nouveautes');
        if (!\array_key_exists($sort, ProductRepository::SORTS)) {
            $sort = 'nouveautes';
        }

        $filters = [
            'q' => mb_substr($query, 0, 80),
            'tri' => $sort,
            'stock' => $request->query->getBoolean('stock'),
            'promo' => $request->query->getBoolean('promo'),
        ];

        $listing = $this->productRepository->findForListing(
            $category,
            $filters['q'],
            $sort,
            $filters['stock'],
            $filters['promo'],
            max(1, $request->query->getInt('page', 1)),
        );

        return $this->render($template, [
            'category' => $category,
            'products' => $listing['items'],
            'listing' => $listing,
            'filters' => $filters,
            'query' => $filters['q'],
            'sorts' => ProductRepository::SORTS,
            'categories' => $this->categoryRepository->findWithActiveCounts(),
        ]);
    }
}
