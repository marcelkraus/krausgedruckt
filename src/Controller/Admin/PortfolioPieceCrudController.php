<?php

namespace App\Controller\Admin;

use App\Entity\PortfolioPiece;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class PortfolioPieceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return PortfolioPiece::class;
    }
}
