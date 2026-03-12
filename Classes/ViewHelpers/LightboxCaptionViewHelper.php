<?php

declare(strict_types=1);

namespace OliverThiele\OtGallery\ViewHelpers;

use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Builds a plain-text lightbox caption from configured image metadata fields.
 *
 * Fields are joined with ' | ' and HTML-escaped for safe use in data attributes.
 *
 * Usage: {ot:lightboxCaption(image: image, fields: gallery.lightboxCaptionFields)}
 */
final class LightboxCaptionViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('image', FileInterface::class, 'The image file object', true);
        $this->registerArgument('fields', 'array', 'Associative array mapping field names to bool (enabled/disabled)', true);
    }

    public function render(): string
    {
        /** @var FileInterface $image */
        $image = $this->arguments['image'];
        /** @var array<string, bool> $fields */
        $fields = $this->arguments['fields'];

        $parts = [];

        if (!empty($fields['title'])) {
            $title = (string)$image->getProperty('title');
            if ($title !== '') {
                $parts[] = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5);
            }
        }

        if (!empty($fields['description'])) {
            $description = (string)$image->getProperty('description');
            if ($description !== '') {
                $parts[] = htmlspecialchars($description, ENT_QUOTES | ENT_HTML5);
            }
        }

        if (!empty($fields['copyright'])) {
            $copyright = (string)$image->getProperty('copyright');
            if ($copyright !== '') {
                $parts[] = '© ' . htmlspecialchars($copyright, ENT_QUOTES | ENT_HTML5);
            }
        }

        return implode(' | ', $parts);
    }
}