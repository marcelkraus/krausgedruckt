<?php

namespace App\Controller\Admin;

use App\Entity\FaqEntry;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Uid\Uuid;

class FaqEntryCrudController extends AbstractCrudController
{
    public function __construct(
        private AdminUrlGenerator $adminUrlGenerator
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

                    return sprintf(
                        '<a href="%s" class="btn btn-sm btn-secondary" title="Nach oben">↑</a> <a href="%s" class="btn btn-sm btn-secondary" title="Nach unten">↓</a>',
                        $upUrl,
                        $downUrl
                    );
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

    public function moveUp(AdminContext $context, EntityManagerInterface $entityManager, AdminUrlGenerator $adminUrlGenerator): RedirectResponse
    {
        return $this->swapSortOrder($context, $entityManager, $adminUrlGenerator, 'up');
    }

    public function moveDown(AdminContext $context, EntityManagerInterface $entityManager, AdminUrlGenerator $adminUrlGenerator): RedirectResponse
    {
        return $this->swapSortOrder($context, $entityManager, $adminUrlGenerator, 'down');
    }

    private function swapSortOrder(AdminContext $context, EntityManagerInterface $entityManager, AdminUrlGenerator $adminUrlGenerator, string $direction): RedirectResponse
    {
        $entityId = $context->getRequest()->query->get('entityId');
        $faqEntry = $entityManager->getRepository(FaqEntry::class)->find($entityId);

        if (!$faqEntry) {
            throw $this->createNotFoundException('FaqEntry not found');
        }

        $currentOrder = $faqEntry->getSortOrder();

        $adjacentFaqEntry = $this->findAdjacentEntry($entityManager, $currentOrder, $direction);

        if ($adjacentFaqEntry) {
            $adjacentOrder = $adjacentFaqEntry->getSortOrder();
            $adjacentFaqEntry->setSortOrder($currentOrder);
            $faqEntry->setSortOrder($adjacentOrder);

            $entityManager->flush();
        }

        $url = $adminUrlGenerator
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
