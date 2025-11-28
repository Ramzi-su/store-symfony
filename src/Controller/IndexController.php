<?php

namespace App\Controller;

use App\Repository\PostRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class IndexController extends AbstractController
{
    private ProductRepository $productRepository;
    private PostRepository $postRepository;

    public function __construct(ProductRepository $productRepository, PostRepository $postRepository)
    {
        $this->productRepository = $productRepository;
        $this->postRepository = $postRepository;
    }

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $user = $this->getUser(); // ✅ Pas besoin d'injecter Security

        if ($user && in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return $this->redirectToRoute('admin_dashboard');
        }

        $mobiles = $this->productRepository->findBy(['category' => 'mobile'], ['createdAt' => 'DESC'], 6);
        $watches = $this->productRepository->findBy(['category' => 'watch'], ['createdAt' => 'DESC'], 6);
        $posts = $this->postRepository->findBy([], ['createdAt' => 'DESC'], 3);

        return $this->render('index/index.html.twig', [
            'mobiles' => $mobiles,
            'watches' => $watches,
            'posts' => $posts,
        ]);
    }
}
