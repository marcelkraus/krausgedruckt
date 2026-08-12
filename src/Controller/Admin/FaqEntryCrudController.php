<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\FaqEntry;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * @extends AbstractCrudController<FaqEntry>
 */
final class FaqEntryCrudController extends AbstractCrudController
{
    private const SORT_CSRF_TOKEN_IDENTIFIER = 'faq-entry-sort';

    public function __construct(
        private AdminUrlGenerator $adminUrlGenerator,
        private CsrfTokenManagerInterface $csrfTokenManager
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return FaqEntry::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Frage/Antwort')
            ->setEntityLabelInPlural('FAQ')
            ->setDefaultSort(['sortOrder' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield BooleanField::new('isVisible', 'Sichtbar');

        if (Crud::PAGE_INDEX === $pageName) {
            yield TextField::new('sortButtons', 'Reihenfolge')
                ->renderAsHtml()
                ->formatValue(function ($value, FaqEntry $entity) {
                    $upUrl = $this->adminUrlGenerator
                        ->setController(self::class)
                        ->setAction('moveUp')
                        ->setEntityId($entity->getId())
                        ->generateUrl();

                    $downUrl = $this->adminUrlGenerator
                        ->setController(self::class)
                        ->setAction('moveDown')
                        ->setEntityId($entity->getId())
                        ->generateUrl();

                    // Both actions change state, so they are submitted as forms
                    // carrying a token instead of plain links.
                    return $this->renderSortForm($upUrl, 'Nach oben', '↑')
                        . ' '
                        . $this->renderSortForm($downUrl, 'Nach unten', '↓');
                });
        }

        yield TextField::new('question', 'Frage')
            ->setRequired(true);

        yield TextareaField::new('answer', 'Antwort')
            ->setRequired(true)
            ->hideOnIndex();

        yield DateField::new('createdAt', 'Erstellt am')
            ->onlyOnDetail();
    }

    #[AdminRoute(path: '/{entityId}/move-up', name: 'move_up', options: ['methods' => ['POST']])]
    public function moveUp(AdminContext $context, EntityManagerInterface $entityManager): RedirectResponse
    {
        return $this->swapSortOrder($context, $entityManager, 'up');
    }

    #[AdminRoute(path: '/{entityId}/move-down', name: 'move_down', options: ['methods' => ['POST']])]
    public function moveDown(AdminContext $context, EntityManagerInterface $entityManager): RedirectResponse
    {
        return $this->swapSortOrder($context, $entityManager, 'down');
    }

    private function renderSortForm(string $url, string $title, string $label): string
    {
        $token = $this->csrfTokenManager->getToken(self::SORT_CSRF_TOKEN_IDENTIFIER)->getValue();

        return sprintf(
            '<form method="post" action="%s" class="d-inline">'
                . '<input type="hidden" name="token" value="%s">'
                . '<button type="submit" class="btn btn-sm btn-secondary" title="%s">%s</button>'
                . '</form>',
            htmlspecialchars($url, ENT_QUOTES),
            htmlspecialchars($token, ENT_QUOTES),
            htmlspecialchars($title, ENT_QUOTES),
            $label
        );
    }

    private function swapSortOrder(AdminContext $context, EntityManagerInterface $entityManager, string $direction): RedirectResponse
    {
        $request = $context->getRequest();

        if ($this->isCsrfTokenValid(self::SORT_CSRF_TOKEN_IDENTIFIER, $request->request->get('token')) === false) {
            throw $this->createAccessDeniedException('Invalid CSRF token for the sorting action.');
        }

        $faqEntry = $context->getEntity()->getInstance();

        if ($faqEntry instanceof FaqEntry === false) {
            // EasyAdmin resolves the entity before the action runs, so this
            // only guards against an unexpected context.
            throw new \LogicException('The admin context did not provide a FaqEntry instance.');
        }

        $currentOrder = $faqEntry->getSortOrder();

        $adjacentFaqEntry = $this->findAdjacentEntry($entityManager, $currentOrder, $direction);

        if ($adjacentFaqEntry !== null) {
            $adjacentOrder = $adjacentFaqEntry->getSortOrder();
            $adjacentFaqEntry->setSortOrder($currentOrder);
            $faqEntry->setSortOrder($adjacentOrder);

            $entityManager->flush();
        }

        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Crud::PAGE_INDEX)
            ->generateUrl();

        return new RedirectResponse($url);
    }

    private function findAdjacentEntry(EntityManagerInterface $entityManager, int $currentOrder, string $direction): ?FaqEntry
    {
        $queryBuilder = $entityManager->getRepository(FaqEntry::class)->createQueryBuilder('f');

        if ($direction === 'up') {
            $queryBuilder->where('f.sortOrder < :currentOrder')
                ->orderBy('f.sortOrder', 'DESC');
        } else {
            $queryBuilder->where('f.sortOrder > :currentOrder')
                ->orderBy('f.sortOrder', 'ASC');
        }

        return $queryBuilder
            ->setParameter('currentOrder', $currentOrder)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
