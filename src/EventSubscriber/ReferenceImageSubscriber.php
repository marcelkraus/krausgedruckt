<?php

namespace App\EventSubscriber;

use App\Entity\Reference;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsDoctrineListener(event: Events::postPersist)]
class ReferenceImageSubscriber
{
    public function __construct(
        private SluggerInterface $slugger,
        private string $projectDir
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Reference) {
            return;
        }

        // If there's a temporary image file, rename it with the proper UUID
        $image = $entity->getImage();
        if ($image && str_contains($image, 'temp_')) {
            $this->renameImageWithUuid($entity);
        }
    }

    private function renameImageWithUuid(Reference $reference): void
    {
        $oldImage = $reference->getImage();
        if (!$oldImage) {
            return;
        }

        $imageDir = $this->projectDir . '/public/images/references/';
        $oldPath = $imageDir . $oldImage;

        if (!file_exists($oldPath)) {
            return;
        }

        // Get extension from old filename
        $extension = pathinfo($oldImage, PATHINFO_EXTENSION);

        // Generate new filename with UUID
        $slug = $this->slugger->slug($reference->getTitle())->lower()->toString();
        $uuid = str_replace('-', '', $reference->getId()->toRfc4122());
        $newImage = sprintf('%s-%s.%s', $slug, $uuid, $extension);
        $newPath = $imageDir . $newImage;

        // Rename file
        if (rename($oldPath, $newPath)) {
            $reference->setImage($newImage);
            // Note: We don't persist here as it would trigger another flush
            // The image property will be updated in memory and saved on next flush
        }
    }
}
