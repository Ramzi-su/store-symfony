<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AboutController extends AbstractController
{
    #[Route('/about', name: 'app_about')]
    public function index(): Response
    {
        // Récupérer le nom du projet depuis les paramètres
        $projectName = $this->getParameter('app.project_name');

        // Passer la variable à la vue Twig
        return $this->render('about/index.html.twig', [
            'project_name' => $projectName,  // Ajout de la variable à la vue
        ]);
    }
}
