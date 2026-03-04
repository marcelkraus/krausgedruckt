<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Entity\FaqEntry;
use App\Entity\Reference;
use App\Controller\Admin\FaqEntryCrudController;
use App\Controller\Admin\ReferenceCrudController;
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
        yield MenuItem::linkToCrud('Referenzen', 'fa fa-images', Reference::class);
        yield MenuItem::linkToCrud('Kategorien', 'fa fa-tags', Category::class);
        yield MenuItem::linkToCrud('FAQ', 'fa fa-question-circle', FaqEntry::class);
    }
}
