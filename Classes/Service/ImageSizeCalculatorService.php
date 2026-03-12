<?php

declare(strict_types=1);

namespace OliverThiele\OtGallery\Service;

/**
 * Calculates optimal image sizes for srcset based on SiteSet configuration.
 * Single source of truth used by both the DataProcessor and the CLI command.
 */
final class ImageSizeCalculatorService
{
    /**
     * Calculates all required image widths for pre-processing and srcset generation.
     *
     * @param array<string, mixed> $settings SiteSet settings (otGallery.*)
     * @param array<string, mixed> $record tt_content record (for per-CE column overrides)
     * @return array<string, int> Map of breakpoint key => pixel width
     */
    public function calculateImageWidths(array $settings, array $record = []): array
    {
        $breakpoints = ['sm', 'md', 'lg', 'xl', 'xxl'];
        $gutter = (int)($settings['otGallery']['grid']['gutter'] ?? 24);
        $containerPadding = (int)($settings['otGallery']['grid']['container']['padding'] ?? 12) * 2;
        $hiDpi = (bool)($settings['otGallery']['processing']['hiDpi'] ?? true);

        $widths = [];

        foreach ($breakpoints as $breakpoint) {
            $columns = $this->getColumnsForBreakpoint($breakpoint, $settings, $record);
            $containerMaxWidth = (int)($settings['otGallery']['grid']['container'][$breakpoint]['maxWidth'] ?? 0);

            if ($containerMaxWidth === 0) {
                continue;
            }

            $availableWidth = $containerMaxWidth - $containerPadding;
            $gutterTotal = ($columns - 1) * $gutter;
            $imageWidth = (int)ceil(($availableWidth - $gutterTotal) / $columns);

            $widths[$breakpoint] = $imageWidth;

            if ($hiDpi) {
                $widths[$breakpoint . '@2x'] = $imageWidth * 2;
            }
        }

        // xs: viewport-based, use 375px as reference for pre-processing
        $xsColumns = $this->getColumnsForBreakpoint('xs', $settings, $record);
        $widths['xs'] = 375 - $containerPadding;
        if ($hiDpi) {
            $widths['xs@2x'] = $widths['xs'] * 2;
        }

        return $widths;
    }

    /**
     * Generates the HTML sizes attribute string for the img tag.
     *
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $record
     */
    public function generateSizesAttribute(array $settings, array $record = []): string
    {
        $gutter = (int)($settings['otGallery']['grid']['gutter'] ?? 24);
        $containerPadding = (int)($settings['otGallery']['grid']['container']['padding'] ?? 12) * 2;

        $breakpointMinWidths = [
            'xxl' => 1400,
            'xl' => 1200,
            'lg' => 992,
            'md' => 768,
            'sm' => 576,
        ];

        $parts = [];

        foreach ($breakpointMinWidths as $breakpoint => $minWidth) {
            $columns = $this->getColumnsForBreakpoint($breakpoint, $settings, $record);
            $containerMaxWidth = (int)($settings['otGallery']['grid']['container'][$breakpoint]['maxWidth'] ?? 0);

            if ($containerMaxWidth === 0) {
                continue;
            }

            $availableWidth = $containerMaxWidth - $containerPadding;
            $gutterTotal = ($columns - 1) * $gutter;
            $imageWidth = (int)ceil(($availableWidth - $gutterTotal) / $columns);

            $parts[] = sprintf('(min-width: %dpx) %dpx', $minWidth, $imageWidth);
        }

        // xs fallback
        $parts[] = sprintf('calc(100vw - %dpx)', $containerPadding);

        return implode(', ', $parts);
    }

    /**
     * Generates Bootstrap row-cols-* CSS classes based on configured column counts.
     *
     * @param array<string, mixed> $settings SiteSet settings (otGallery.*)
     * @param array<string, mixed> $record tt_content record (for per-CE column overrides)
     * @return string e.g. "row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 row-cols-xxl-6"
     */
    public function generateRowColsClasses(array $settings, array $record = []): string
    {
        $breakpoints = [
            'xs' => '',
            'sm' => 'sm-',
            'md' => 'md-',
            'lg' => 'lg-',
            'xl' => 'xl-',
            'xxl' => 'xxl-',
        ];

        $classes = [];
        foreach ($breakpoints as $breakpoint => $prefix) {
            $columns = $this->getColumnsForBreakpoint($breakpoint, $settings, $record);
            $classes[] = 'row-cols-' . $prefix . $columns;
        }

        return implode(' ', $classes);
    }

    /**
     * Returns a MD5 hash of the configuration relevant for image processing.
     * Used by CLI to detect when reprocessing is needed.
     *
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $record
     */
    public function calculateConfigHash(array $settings, array $record = []): string
    {
        $relevant = [
            'columns' => [
                'xs' => $this->getColumnsForBreakpoint('xs', $settings, $record),
                'sm' => $this->getColumnsForBreakpoint('sm', $settings, $record),
                'md' => $this->getColumnsForBreakpoint('md', $settings, $record),
                'lg' => $this->getColumnsForBreakpoint('lg', $settings, $record),
                'xl' => $this->getColumnsForBreakpoint('xl', $settings, $record),
                'xxl' => $this->getColumnsForBreakpoint('xxl', $settings, $record),
            ],
            'gutter' => $settings['otGallery']['grid']['gutter'] ?? 24,
            'containerPadding' => $settings['otGallery']['grid']['container']['padding'] ?? 12,
            'hiDpi' => $settings['otGallery']['processing']['hiDpi'] ?? true,
            'webpQuality' => $settings['otGallery']['processing']['webp']['quality'] ?? 82,
            'jpegQuality' => $settings['otGallery']['processing']['jpeg']['quality'] ?? 85,
            'aspectRatio' => $record['tx_otgallery_aspect_ratio'] ?? 'free',
            'rendering' => $record['tx_otgallery_rendering'] ?? 'cover',
        ];

        return md5(serialize($relevant));
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $record
     */
    private function getColumnsForBreakpoint(string $breakpoint, array $settings, array $record): int
    {
        // Per-CE override takes precedence
        if (!empty($record['tx_otgallery_columns_override'])) {
            $fieldName = 'tx_otgallery_columns_' . $breakpoint;
            if (isset($record[$fieldName]) && (int)$record[$fieldName] > 0) {
                return (int)$record[$fieldName];
            }
        }

        return max(1, (int)($settings['otGallery']['grid']['columns'][$breakpoint] ?? 1));
    }
}
