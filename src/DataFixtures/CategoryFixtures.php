<?php

namespace App\DataFixtures;

use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CategoryFixtures extends Fixture
{
    public const CATEGORY_3D_DRUCKER = 'category-3d-drucker';
    public const CATEGORY_RC_MODELLBAU = 'category-rc-modellbau';
    public const CATEGORY_COSPLAY = 'category-cosplay';
    public const CATEGORY_WERKSTATT = 'category-werkstatt';
    public const CATEGORY_AUTOS = 'category-autos';

    public function load(ObjectManager $manager): void
    {
        $categories = [
            self::CATEGORY_3D_DRUCKER => '3D-Drucker & Zubehör',
            self::CATEGORY_RC_MODELLBAU => 'RC-Modellbau',
            self::CATEGORY_COSPLAY => 'Cosplay',
            self::CATEGORY_WERKSTATT => 'Werkstatt',
            self::CATEGORY_AUTOS => 'Autos',
        ];

        foreach ($categories as $ref => $name) {
            $category = new Category();
            $category->setName($name);

            $manager->persist($category);
            $this->addReference($ref, $category);
        }

        $manager->flush();
    }
}
