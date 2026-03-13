<?php

declare(strict_types=1);

namespace OliverThiele\OtGallery\ViewHelpers;

use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Returns the processed image URL for a single width.
 *
 * Uses identical processing instructions as GallerySrcsetViewHelper so that
 * CLI pre-processing (gallery:process) produces an exact cache hit — no extra
 * file is created on the first frontend page load.
 *
 * Usage:
 * <img src="{ot:galleryImageSrc(image: image, width: gallery.imageWidths.xxl)}" ... />
 */
final class GalleryImageSrcViewHelper extends AbstractViewHelper
{
    public function __construct(private readonly ImageService $imageService)
    {
    }

    public function initializeArguments(): void
    {
        $this->registerArgument('image', FileInterface::class, 'File or FileReference object', true);
        $this->registerArgument('width', 'int', 'Target width in pixels', true);
        $this->registerArgument('fileExtension', 'string', 'Target format', false, 'webp');
    }

    public function render(): string
    {
        try {
            $processedImage = $this->imageService->applyProcessingInstructions(
                $this->arguments['image'],
                [
                    'width'         => $this->arguments['width'],
                    'fileExtension' => $this->arguments['fileExtension'],
                ]
            );
            return $this->imageService->getImageUri($processedImage);
        } catch (\Exception) {
            return (string)($this->arguments['image']->getPublicUrl() ?? '');
        }
    }
}