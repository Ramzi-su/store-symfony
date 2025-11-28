<?php

namespace App\Controller;

use App\Repository\PostRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class BlogController extends AbstractController
{
    #[Route('/blog', name: 'app_blog')]
    public function index(PostRepository $postRepository, Request $request, PaginatorInterface $paginator): Response
    {
        // Requête de base pour les articles
        $query = $postRepository->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery();

        // Pagination (6 articles par page)
        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            6
        );

        // Articles récents (sidebar)
        $recentPosts = $postRepository->findBy([], ['createdAt' => 'DESC'], 5);

        // Catégories simulées (à remplacer par une vraie table si tu veux)
        $blogCategories = [
            ['slug' => 'gadgets', 'name' => 'Gadgets', 'count' => 5],
            ['slug' => 'technology', 'name' => 'Technology', 'count' => 8],
            ['slug' => 'camera', 'name' => 'Camera', 'count' => 3],
            ['slug' => 'smartphones', 'name' => 'Smartphones', 'count' => 6],
            ['slug' => 'accessories', 'name' => 'Accessories', 'count' => 2],
        ];

        // Tags simulés
        $blogTags = [
            ['slug' => 'gadgets', 'name' => 'Gadgets'],
            ['slug' => 'technology', 'name' => 'Technology'],
            ['slug' => 'camera', 'name' => 'Camera'],
            ['slug' => 'smartphones', 'name' => 'Smartphones'],
            ['slug' => 'accessories', 'name' => 'Accessories'],
            ['slug' => 'electronics', 'name' => 'Electronics'],
        ];

        return $this->render('blog/blog.html.twig', [
            'posts' => $pagination,
            'current_page' => $pagination->getCurrentPageNumber(),
            'total_pages' => ceil($pagination->getTotalItemCount() / $pagination->getItemNumberPerPage()),
            'recent_posts' => $recentPosts,
            'blog_categories' => $blogCategories,
            'blog_tags' => $blogTags,
        ]);
    }
}
