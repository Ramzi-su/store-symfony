<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\Orders;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/admin', routeName: 'admin_dashboard')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        // Redirige directement vers la page des Produits en entrant dans l'admin
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
        return $this->redirect($adminUrlGenerator->setController(ProductCrudController::class)->generateUrl());
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Boutique Admin');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToRoute('Retour au Site', 'fa fa-arrow-left', 'app_home');
        
        yield MenuItem::section('Boutique');
        yield MenuItem::linkToCrud('Produits', 'fas fa-box', Product::class);
        yield MenuItem::linkToCrud('Commandes', 'fas fa-shopping-cart', Orders::class);
        
        yield MenuItem::section('Clients');
        yield MenuItem::linkToCrud('Utilisateurs', 'fas fa-users', User::class);
    }
}
