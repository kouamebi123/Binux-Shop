<?php

namespace App\Service;

use App\Entity\Category;
use App\Entity\Product;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Adresses lisibles et uniques : « lampe », puis « lampe-2 » si le nom existe déjà.
 */
class SlugGenerator
{
    public function __construct(
        private readonly SluggerInterface $slugger,
        private readonly ProductRepository $products,
        private readonly CategoryRepository $categories,
    ) {
    }

    public function forProduct(Product $product): string
    {
        return $this->unique((string) $product->getName(), fn (string $slug) => $this->products->slugExists($slug, $product->getId()));
    }

    public function forCategory(Category $category): string
    {
        return $this->unique((string) $category->getName(), fn (string $slug) => $this->categories->slugExists($slug, $category->getId()));
    }

    /**
     * @param callable(string): bool $exists
     */
    private function unique(string $name, callable $exists): string
    {
        $base = (string) $this->slugger->slug($name)->lower();
        if ('' === $base) {
            $base = 'article';
        }

        // Ces chemins sont déjà pris par des pages du catalogue.
        if (\in_array($base, ['categorie', 'recherche', 'suggestions'], true)) {
            $base .= '-1';
        }

        $slug = $base;
        for ($i = 2; $exists($slug); ++$i) {
            $slug = $base . '-' . $i;
        }

        return $slug;
    }
}
