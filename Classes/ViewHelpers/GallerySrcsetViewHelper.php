<?php

declare(strict_types=1);

namespace OliverThiele\OtGallery\ViewHelpers;

use OliverThiele\OtGallery\Service\ImageSizeCalculatorService;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Generates a srcset attribute string for gallery images.
 *
 * Uses pre-processed files if available (via CLI gallery:process),
 * falls back to on-demand processing otherwise.
 *
 * Usage:
 * <ot:gallerySrcset image="{image}" widths="{gallery.imageWidths}" />
 */
final class GallerySrcsetViewHelper extends AbstractViewHelper
{
    public function __construct(
        private readonly ImageService $imageService,
        private readonly ImageSizeCalculatorService $imageSizeCalculatorService,
    ) {
    }

    public function initializeArguments(): void
    {
        $this->registerArgument('image', FileInterface::class, 'File object', true);
        $this->registerArgument('widths', 'array', 'Map of breakpoint => pixel width', true);
        $this->registerArgument('fileExtension', 'string', 'Target format (webp recommended)', false, 'webp');
    }

    public function render(): string
    {
        $image = $this->arguments['image'];
        $widths = $this->arguments['widths'];
        $fileExtension = $this->arguments['fileExtension'];

        $uniqueWidths = $this->imageSizeCalculatorService->collectUniqueWidths($widths);

        if (empty($uniqueWidths)) {
            return '';
        }

        $srcsetParts = [];

        foreach ($uniqueWidths as $width) {
            try {
                $processedImage = $this->imageService->applyProcessingInstructions(
                    $image,
                    ['width' => $width, 'fileExtension' => $fileExtension]
                );
                $url = $this->imageService->getImageUri($processedImage);
                $srcsetParts[] = $url . ' ' . $width . 'w';
            } catch (\Exception) {
                // Skip sizes that fail to process
            }
        }

        return implode(', ', $srcsetParts);
    }

}
