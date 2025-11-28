<?php

namespace App\Controller;

use App\Entity\Post;
use App\Repository\PostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class SinglePostController extends AbstractController
{
    #[Route('/blog/{slug}', name: 'app_single_post')]
    public function show(string $slug, PostRepository $postRepository): Response
    {
        $post = $postRepository->findOneBy(['slug' => $slug]);

        if (!$post) {
            throw $this->createNotFoundException('Article introuvable');
        }

        return $this->render('single_post/single_post.html.twig', [
            'post' => $post,
        ]);
    }
}
