<?php

namespace App\Controller\Admin;

use App\Entity\Reference;
use App\Enum\Material;
use App\Enum\Printer;
use App\Service\InstagramCaptionBuilder;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\Response;
use Vich\UploaderBundle\Form\Type\VichImageType;

/**
 * @extends AbstractCrudController<Reference>
 */
class ReferenceCrudController extends AbstractCrudController
{
    public function __construct(
        private InstagramCaptionBuilder $instagramCaptionBuilder
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Reference::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Referenz')
            ->setEntityLabelInPlural('Referenzen')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $instagramPreview = Action::new('instagramPreview', 'Instagram-Vorschau', 'fa fa-share-nodes')
            ->linkToCrudAction('instagramPreview');

        return $actions
            ->add(Crud::PAGE_INDEX, $instagramPreview)
            ->add(Crud::PAGE_DETAIL, $instagramPreview);
    }

    #[AdminRoute(path: '/{entityId}/instagram-preview', name: 'instagram_preview', options: ['methods' => ['GET']])]
    public function instagramPreview(AdminContext $context): Response
    {
        $reference = $context->getEntity()->getInstance();

        if ($reference instanceof Reference === false) {
            throw new \LogicException('The admin context did not provide a Reference instance.');
        }

        return $this->render('admin/instagram-preview.html.twig', [
            'reference' => $reference,
            'caption' => $this->instagramCaptionBuilder->build($reference),
        ]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield BooleanField::new('isVisible', 'Sichtbar');

        yield TextField::new('title', 'Titel')
            ->setRequired(true);

        if (Crud::PAGE_NEW === $pageName) {
            // The slug is derived from the title while a reference is being
            // created, so the field only shows what will be stored.
            yield SlugField::new('slug', 'Slug')
                ->setTargetFieldName('title')
                ->setFormTypeOption('disabled', true)
                ->setHelp('Wird aus dem Titel erzeugt und ist nach dem Anlegen änderbar.')
                ->hideOnIndex();
        } else {
            yield TextField::new('slug', 'Slug')
                ->setHelp('Ändert die Adresse der Detailseite. Kleinbuchstaben, Ziffern und Bindestriche.')
                ->hideOnIndex();
        }

        yield TextField::new('summary', 'Zusammenfassung')
            ->setMaxLength(250)
            ->hideOnIndex();

        yield TextareaField::new('description', 'Beschreibung')
            ->setRequired(true)
            ->setNumOfRows(10)
            ->hideOnIndex();

        yield AssociationField::new('category', 'Kategorie')
            // setSortProperty() only orders the index list by the category
            // name. The choices in the form need their own query builder.
            ->setSortProperty('name')
            ->setRequired(true)
            ->setQueryBuilder(
                static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->orderBy('entity.name', 'ASC')
            );

        yield ChoiceField::new('material', 'Material')
            ->setChoices($this->buildEnumChoices(Material::cases()));

        yield ChoiceField::new('printer', 'Drucker')
            ->setChoices($this->buildEnumChoices(Printer::cases()));

        yield ImageField::new('imageLandscape', 'Bild')
            ->setBasePath('/images/references/landscape')
            ->onlyOnIndex();

        yield TextField::new('imageFileLandscape', 'Bild im Querformat')
            ->setFormType(VichImageType::class)
            ->setFormTypeOptions([
                'allow_delete' => false,
                'download_uri' => false,
            ])
            ->setHelp('Seitenverhältnis 5:4, wird auf 1080 × 864 Pixel verkleinert.')
            ->onlyOnForms();

        yield TextField::new('imageFilePortrait', 'Bild im Hochformat')
            ->setFormType(VichImageType::class)
            ->setFormTypeOptions([
                'allow_delete' => false,
                'download_uri' => false,
            ])
            ->setHelp('Seitenverhältnis 4:5, wird auf 1080 × 1350 Pixel verkleinert.')
            ->onlyOnForms();

        yield TextField::new('source.title', 'Quelle: Titel')
            ->hideOnIndex();

        yield TextField::new('source.url', 'Quelle: URL')
            ->hideOnIndex();

        yield TextField::new('source.author', 'Quelle: Autor')
            ->hideOnIndex();

        yield TextField::new('ratingUrl', 'Google-Bewertung: URL')
            ->hideOnIndex();

        yield DateField::new('createdAt', 'Erstellt am')
            ->setFormTypeOptions([
                'years' => range(date('Y') - 10, date('Y') + 1),
            ]);
    }

    /**
     * EasyAdmin falls back to the case name when it receives bare enum cases,
     * so the choices are keyed by their value instead.
     *
     * @param \BackedEnum[] $cases
     *
     * @return array<string, \BackedEnum>
     */
    private function buildEnumChoices(array $cases): array
    {
        $choices = [];

        foreach ($cases as $case) {
            $choices[$case->value] = $case;
        }

        return $choices;
    }
}
