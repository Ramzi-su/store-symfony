<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ShopController extends AbstractController
{
    #[Route('/shop', name: 'app_shop')]
    public function index(Request $request, ProductRepository $productRepository, EntityManagerInterface $em): Response
    {
        $category = $request->query->get('category');

        // ✅ Gestion robuste : string ou array → toujours tableau
        $tagParam = $request->query->get('tag');
        $selectedTags = is_array($tagParam) ? $tagParam : ($tagParam ? [$tagParam] : []);

        $brandParam = $request->query->get('brand');
        $selectedBrands = is_array($brandParam) ? $brandParam : ($brandParam ? [$brandParam] : []);

        $colorParam = $request->query->get('color');
        $selectedColors = is_array($colorParam) ? $colorParam : ($colorParam ? [$colorParam] : []);

        // Critères de filtrage de base
        $criteria = [];
        if ($category) {
            $criteria['category'] = $category;
        }

        $products = $productRepository->findBy($criteria, ['createdAt' => 'DESC']);
        $totalProducts = count($products);

        // ✅ Catégories dynamiques
        $categoriesResult = $productRepository->createQueryBuilder('p')
            ->select('DISTINCT p.category AS name')
            ->orderBy('p.category', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $categories = array_map(fn($c) => [
            'slug' => $c['name'],
            'name' => ucfirst($c['name']),
            'count' => $productRepository->count(['category' => $c['name']])
        ], $categoriesResult);

        // 🧪 Marques fictives (à remplacer par tes entités)
        $brands = [
            ['slug' => 'apple', 'name' => 'Apple', 'count' => 12],
            ['slug' => 'samsung', 'name' => 'Samsung', 'count' => 8],
        ];

        $colors = [
            ['slug' => 'black', 'name' => 'Black', 'hex' => '#000', 'count' => 7],
            ['slug' => 'white', 'name' => 'White', 'hex' => '#fff', 'count' => 5],
        ];

        $tags = [
            ['slug' => 'promo', 'name' => 'Promo'],
            ['slug' => 'new', 'name' => 'Nouveau'],
        ];

        return $this->render('shop/shop.html.twig', [
            'products' => $products,
            'categories' => $categories,
            'category' => $category ?? 'all',
            'brands' => $brands,
            'colors' => $colors,
            'tags' => $tags,
            'selected_brands' => $selectedBrands,
            'selected_colors' => $selectedColors,
            'selected_tags' => $selectedTags,
            'total_products' => $totalProducts,
            'current_page' => 1,
            'total_pages' => 1,
        ]);
    }
}
