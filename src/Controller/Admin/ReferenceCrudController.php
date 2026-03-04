<?php

namespace App\Controller\Admin;

use App\Entity\Reference;
use App\Enum\Material;
use App\Enum\Printer;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Vich\UploaderBundle\Form\Type\VichImageType;

class ReferenceCrudController extends AbstractCrudController
{
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

    public function configureFields(string $pageName): iterable
    {
        yield BooleanField::new('isVisible', 'Sichtbar');

        yield TextField::new('title', 'Titel')
            ->setRequired(true);

        yield SlugField::new('slug', 'Slug')
            ->setTargetFieldName('title')
            ->hideOnIndex();

        yield TextField::new('summary', 'Zusammenfassung')
            ->setMaxLength(250)
            ->hideOnIndex();

        yield TextareaField::new('description', 'Beschreibung')
            ->setRequired(true)
            ->hideOnIndex();

        yield AssociationField::new('category', 'Kategorie')
            ->setSortProperty('name');

        yield ChoiceField::new('material', 'Material')
            ->setChoices(Material::cases());

        yield ChoiceField::new('printer', 'Drucker')
            ->setChoices(Printer::cases());

        yield ImageField::new('image', 'Bild')
            ->setBasePath('/images/references')
            ->onlyOnIndex();

        yield TextField::new('imageFile', 'Bild hochladen')
            ->setFormType(VichImageType::class)
            ->setFormTypeOptions([
                'allow_delete' => false,
                'download_uri' => false,
            ])
            ->onlyOnForms();

        yield TextField::new('source.title', 'Quelle: Titel')
            ->hideOnIndex();

        yield TextField::new('source.url', 'Quelle: URL')
            ->hideOnIndex();

        yield TextField::new('source.author', 'Quelle: Autor')
            ->hideOnIndex();

        yield DateField::new('createdAt', 'Erstellt am')
            ->setFormTypeOptions([
                'years' => range(date('Y') - 10, date('Y') + 1),
            ]);
    }
}
