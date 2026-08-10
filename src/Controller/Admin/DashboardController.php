<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private AdminUrlGenerator $adminUrlGenerator
    ) {
    }

    public function index(): Response
    {
        $referenceIndexUrl = $this->adminUrlGenerator
            ->setController(ReferenceCrudController::class)
            ->setAction('index')
            ->generateUrl();

        $faqIndexUrl = $this->adminUrlGenerator
            ->setController(FaqEntryCrudController::class)
            ->setAction('index')
            ->generateUrl();

        return $this->render('admin/dashboard.html.twig', [
            'referenceIndexUrl' => $referenceIndexUrl,
            'faqIndexUrl' => $faqIndexUrl,
        ]);
    }

    #[Route('/admin/logout', name: 'admin_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('krausgedruckt:admin');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkTo(ReferenceCrudController::class, 'Referenzen', 'fa fa-images');
        yield MenuItem::linkTo(CategoryCrudController::class, 'Kategorien', 'fa fa-tags');
        yield MenuItem::linkTo(FaqEntryCrudController::class, 'FAQ', 'fa fa-question-circle');
    }
}
