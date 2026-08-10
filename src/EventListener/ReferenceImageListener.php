<?php

namespace App\EventListener;

use App\Entity\Reference;
use App\Service\ImageNormalizer;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Vich\UploaderBundle\Event\Event;
use Vich\UploaderBundle\Event\Events;
use Vich\UploaderBundle\Mapping\PropertyMapping;

/**
 * Keeps the stored reference images in shape. VichUploader has already moved
 * the file into place when these events fire, so the listener works on the
 * final path.
 */
class ReferenceImageListener
{
    private const FILTER_SETS = ['reference_landscape', 'reference_portrait'];

    public function __construct(
        private ImageNormalizer $imageNormalizer,
        private CacheManager $cacheManager,
        private LoggerInterface $logger,
        private RequestStack $requestStack
    ) {
    }

    #[AsEventListener(event: Events::POST_UPLOAD)]
    public function onPostUpload(Event $event): void
    {
        $target = $this->resolveTarget($event);

        if ($target === null) {
            return;
        }

        [$path, $uri, $width, $height] = $target;

        try {
            $this->imageNormalizer->normalize($path, $width, $height);
        } catch (\Throwable $exception) {
            // A picture that cannot be processed is kept at its original size
            // rather than aborting the flush, which would leave the file on
            // disk without a record pointing at it. The failure has to reach
            // the editor though, otherwise a broken image pipeline looks like
            // a successful upload.
            $this->logger->error('Could not normalise a reference image.', [
                'path' => $path,
                'exception' => $exception,
            ]);

            $this->announceFailure();
        }

        // The file name is derived from the title and the identifier, so it
        // stays the same when an image is replaced. Without dropping the
        // rendered versions the list would keep showing the previous picture.
        $this->cacheManager->remove($uri, self::FILTER_SETS);
    }

    #[AsEventListener(event: Events::POST_REMOVE)]
    public function onPostRemove(Event $event): void
    {
        $target = $this->resolveTarget($event);

        if ($target === null) {
            return;
        }

        $this->cacheManager->remove($target[1], self::FILTER_SETS);
    }

    private function announceFailure(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null || $request->hasSession() === false) {
            return;
        }

        $session = $request->getSession();

        if ($session instanceof FlashBagAwareSessionInterface === false) {
            return;
        }

        $session->getFlashBag()->add(
            'warning',
            'Das Bild wurde gespeichert, konnte aber nicht auf die Zielgröße gebracht werden. '
            . 'Bitte "bin/console app:normalize-reference-images" ausführen.'
        );
    }

    /**
     * @return array{0: string, 1: string, 2: int, 3: int}|null
     */
    private function resolveTarget(Event $event): ?array
    {
        if ($event->getObject() instanceof Reference === false) {
            return null;
        }

        $mapping = $event->getMapping();

        $size = match ($mapping->getMappingName()) {
            'reference_images_landscape' => [ImageNormalizer::LANDSCAPE_WIDTH, ImageNormalizer::LANDSCAPE_HEIGHT],
            'reference_images_portrait' => [ImageNormalizer::PORTRAIT_WIDTH, ImageNormalizer::PORTRAIT_HEIGHT],
            default => null,
        };

        if ($size === null) {
            return null;
        }

        $fileName = $this->resolveFileName($event->getObject(), $mapping);

        if ($fileName === null || $fileName === '') {
            return null;
        }

        return [
            $mapping->getUploadDestination() . '/' . $fileName,
            $mapping->getUriPrefix() . '/' . $fileName,
            $size[0],
            $size[1],
        ];
    }

    private function resolveFileName(Reference $reference, PropertyMapping $mapping): ?string
    {
        return $mapping->getMappingName() === 'reference_images_landscape'
            ? $reference->getImageLandscape()
            : $reference->getImagePortrait();
    }
}
