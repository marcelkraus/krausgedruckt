<?php

namespace App\Service;

/**
 * Brings an uploaded reference image into the size used across the site and on
 * Instagram. Both formats are capped at a width of 1080 pixels; the aspect
 * ratio itself is enforced by validation before a file ever reaches this class.
 *
 * The uploaded file is replaced in place, so every transformation here is
 * irreversible and has to be correct on the first attempt.
 */
class ImageNormalizer
{
    public const LANDSCAPE_WIDTH = 1080;
    public const LANDSCAPE_HEIGHT = 864;
    public const PORTRAIT_WIDTH = 1080;
    public const PORTRAIT_HEIGHT = 1350;

    /**
     * @throws \ImagickException          when the file cannot be read or written
     * @throws \InvalidArgumentException when the image is smaller than the target
     */
    public function normalize(string $path, int $targetWidth, int $targetHeight): void
    {
        if (is_file($path) === false) {
            return;
        }

        $image = new \Imagick($path);

        try {
            $this->applyOrientation($image);

            if ($image->getImageWidth() < $targetWidth || $image->getImageHeight() < $targetHeight) {
                throw new \InvalidArgumentException(sprintf(
                    'The image is smaller than the target size of %d × %d pixels.',
                    $targetWidth,
                    $targetHeight
                ));
            }

            if ($image->getImageWidth() !== $targetWidth || $image->getImageHeight() !== $targetHeight) {
                $image->cropThumbnailImage($targetWidth, $targetHeight);
            }

            // The color profile is converted rather than discarded, otherwise
            // a picture exported in Display P3 would be read as sRGB and look
            // washed out.
            $image->transformImageColorspace(\Imagick::COLORSPACE_SRGB);

            // Stripping runs unconditionally, so a file that already matches
            // the target size does not keep its GPS coordinates in a directory
            // served to everyone.
            $image->stripImage();

            if (strtolower($image->getImageFormat()) === 'jpeg') {
                $image->setImageCompressionQuality(85);
            }

            $image->writeImage($path);
        } finally {
            $image->clear();
        }
    }

    /**
     * Rotates the pixels according to the EXIF orientation and resets the flag.
     *
     * The built-in helper is called autoOrientImage() in the ImageMagick 6
     * binding and autoOrient() in the ImageMagick 7 one, so neither name can be
     * relied upon. Doing the transformation by hand works on both, and it has
     * to happen before the metadata is stripped — otherwise the picture ends up
     * lying on its side for good.
     */
    private function applyOrientation(\Imagick $image): void
    {
        $orientation = $image->getImageOrientation();

        switch ($orientation) {
            case \Imagick::ORIENTATION_TOPRIGHT:
                $image->flopImage();
                break;
            case \Imagick::ORIENTATION_BOTTOMRIGHT:
                $image->rotateImage('#000000', 180);
                break;
            case \Imagick::ORIENTATION_BOTTOMLEFT:
                $image->flipImage();
                break;
            case \Imagick::ORIENTATION_LEFTTOP:
                $image->flipImage();
                $image->rotateImage('#000000', 90);
                break;
            case \Imagick::ORIENTATION_RIGHTTOP:
                $image->rotateImage('#000000', 90);
                break;
            case \Imagick::ORIENTATION_RIGHTBOTTOM:
                $image->flopImage();
                $image->rotateImage('#000000', 90);
                break;
            case \Imagick::ORIENTATION_LEFTBOTTOM:
                $image->rotateImage('#000000', 270);
                break;
            default:
                return;
        }

        $image->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
    }
}
