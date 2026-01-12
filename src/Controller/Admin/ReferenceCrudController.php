<?php

namespace App\Controller\Admin;

use App\Entity\Reference;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
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
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield BooleanField::new('isVisible', 'Sichtbar');

        yield TextField::new('title', 'Titel')
            ->setRequired(true);

        yield TextareaField::new('description', 'Beschreibung')
            ->setRequired(true)
            ->hideOnIndex();

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
    }
}
