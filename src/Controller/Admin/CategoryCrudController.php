<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Repository\ReferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<Category>
 */
class CategoryCrudController extends AbstractCrudController
{
    public function __construct(
        private ReferenceRepository $referenceRepository
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Category::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Kategorie')
            ->setEntityLabelInPlural('Kategorien')
            ->setDefaultSort(['name' => 'ASC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        // A category that references point to cannot be deleted, so the button
        // disappears instead of running into a foreign key error.
        return $actions
            ->update(
                Crud::PAGE_INDEX,
                Action::DELETE,
                fn (Action $action): Action => $action->displayIf(
                    fn (Category $category): bool => $this->referenceRepository->countByCategory($category) === 0
                )
            )
            ->update(
                Crud::PAGE_DETAIL,
                Action::DELETE,
                fn (Action $action): Action => $action->displayIf(
                    fn (Category $category): bool => $this->referenceRepository->countByCategory($category) === 0
                )
            );
    }

    public function deleteEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        if ($entityInstance instanceof Category === false) {
            parent::deleteEntity($entityManager, $entityInstance);

            return;
        }

        $referenceCount = $this->referenceRepository->countByCategory($entityInstance);

        if ($referenceCount > 0) {
            // Skipping with a message rather than throwing keeps a batch
            // deletion from stopping halfway through with a bare error page.
            $this->addFlash('danger', sprintf(
                'Die Kategorie „%s“ ist %d Referenzen zugeordnet und wurde nicht gelöscht.',
                $entityInstance->getName(),
                $referenceCount
            ));

            return;
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Name')
            ->setRequired(true);

        if (Crud::PAGE_NEW === $pageName) {
            // The slug is derived from the name while a category is being
            // created, so the field only shows what will be stored.
            yield SlugField::new('slug', 'Slug')
                ->setTargetFieldName('name')
                ->setFormTypeOption('disabled', true)
                ->setHelp('Wird aus dem Namen erzeugt und ist nach dem Anlegen änderbar.');
        } else {
            yield TextField::new('slug', 'Slug')
                ->setHelp('Kleinbuchstaben, Ziffern und Bindestriche.');
        }
    }
}
