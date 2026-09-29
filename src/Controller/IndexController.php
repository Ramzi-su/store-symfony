<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class IndexController extends AbstractController
{
    private const NEW_ARRIVALS = 8;

    #[Route('/', name: 'app_home')]
    public function index(ProductRepository $productRepository): Response
    {
        // Admins see the storefront too; the header links them to the administration.
        return $this->render('index/index.html.twig', [
            'featured' => $productRepository->findFeatured(),
            'categories' => $productRepository->countByCategory(),
            'new_arrivals' => $productRepository->findBy([], ['createdAt' => 'DESC'], self::NEW_ARRIVALS),
        ]);
    }
}
