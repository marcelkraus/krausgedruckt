<?php

namespace App\Service;

use App\Entity\Reference;
use Symfony\Component\String\Slugger\SluggerInterface;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Naming\NamerInterface;

class ReferenceImageNamer implements NamerInterface
{
    public function __construct(
        private SluggerInterface $slugger
    ) {
    }

    /**
     * Creates filename in format: {title-as-slug}-{uuid}.{extension}
     * Example: ein-klassiker-zu-beginn-01934e5f8b127890abcdef1234567890.jpg
     */
    public function name($object, PropertyMapping $mapping): string
    {
        if (!$object instanceof Reference) {
            throw new \InvalidArgumentException('Object must be an instance of Reference');
        }

        $file = $mapping->getFile($object);
        $extension = $file->guessExtension() ?? 'bin';

        // Title as slug
        $slug = $this->slugger->slug($object->getTitle())->lower()->toString();

        // UUID (without dashes for shorter name)
        $id = $object->getId();
        if ($id === null) {
            throw new \LogicException('Reference entity must have an ID before file upload. Ensure UUID is set in constructor.');
        }

        $uuid = str_replace('-', '', $id->toRfc4122());

        return sprintf('%s-%s.%s', $slug, $uuid, $extension);
    }
}
