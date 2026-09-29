<?php

namespace App\Controller;

use App\Catalog\ProductFilters;
use App\Repository\ProductRepository;
use Doctrine\ORM\Tools\Pagination\OffsetPaginator;
use Doctrine\ORM\Tools\Pagination\Window;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShopController extends AbstractController
{
    private const PER_PAGE = 12;

    #[Route('/shop', name: 'app_shop')]
    public function index(Request $request, ProductRepository $productRepository): Response
    {
        $filters = ProductFilters::fromRequest($request);

        // Doctrine's paginator counts and slices the query; it reads nothing from the URL itself
        // (unlike KnpPaginator, which applies ?sort= and ?filterField= on its own).
        $page = (new OffsetPaginator(fetchJoinCollection: false))->paginate(
            $productRepository->createCatalogQuery($filters),
            Window::fromPageNumberAndSize($filters->page, self::PER_PAGE),
        );

        return $this->render('shop/shop.html.twig', [
            'filters' => $filters,
            'page' => $page,
            'categories' => $productRepository->countByCategory(),
            'sorts' => ProductFilters::SORTS,
        ]);
    }
}
