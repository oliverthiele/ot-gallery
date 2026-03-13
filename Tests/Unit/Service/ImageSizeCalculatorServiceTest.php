<?php

declare(strict_types=1);

namespace OliverThiele\OtGallery\Tests\Unit\Service;

use OliverThiele\OtGallery\Service\ImageSizeCalculatorService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for ImageSizeCalculatorService.
 *
 * This service is a pure PHP calculation class with zero TYPO3 dependencies,
 * making it ideal for isolated unit testing without any framework bootstrap.
 */
final class ImageSizeCalculatorServiceTest extends UnitTestCase
{
    private ImageSizeCalculatorService $subject;

    /**
     * Standard SiteSet settings used across multiple tests.
     * Mirrors a typical Bootstrap 5 grid with 6 breakpoints.
     *
     * @var array<string, mixed>
     */
    private array $defaultSettings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new ImageSizeCalculatorService();

        $this->defaultSettings = [
            'otGallery' => [
                'grid' => [
                    'gutter' => 24,
                    'container' => [
                        'padding' => 12,
                        'sm'  => ['maxWidth' => 540],
                        'md'  => ['maxWidth' => 720],
                        'lg'  => ['maxWidth' => 960],
                        'xl'  => ['maxWidth' => 1140],
                        'xxl' => ['maxWidth' => 1320],
                    ],
                    'columns' => [
                        'xs'  => 1,
                        'sm'  => 2,
                        'md'  => 3,
                        'lg'  => 4,
                        'xl'  => 5,
                        'xxl' => 6,
                    ],
                ],
                'processing' => [
                    'hiDpi' => true,
                    'webp'  => ['quality' => 82],
                    'jpeg'  => ['quality' => 85],
                ],
                'lightbox' => [
                    'captionFields' => 'title,description,copyright',
                ],
            ],
        ];
    }

    // =========================================================================
    // calculateImageWidths
    // =========================================================================

    #[Test]
    public function calculateImageWidthsReturnsCorrectWidthsForAllBreakpoints(): void
    {
        // containerPadding = 12 * 2 = 24, gutter = 24
        // sm:  ceil((540-24 - (2-1)*24) / 2) = ceil(492/2)   = 246
        // md:  ceil((720-24 - (3-1)*24) / 3) = ceil(648/3)   = 216
        // lg:  ceil((960-24 - (4-1)*24) / 4) = ceil(864/4)   = 216
        // xl:  ceil((1140-24 - (5-1)*24) / 5) = ceil(1020/5) = 204
        // xxl: ceil((1320-24 - (6-1)*24) / 6) = ceil(1176/6) = 196
        // xs:  375 - 24 = 351
        $result = $this->subject->calculateImageWidths($this->defaultSettings);

        self::assertSame(246, $result['sm']);
        self::assertSame(216, $result['md']);
        self::assertSame(216, $result['lg']);
        self::assertSame(204, $result['xl']);
        self::assertSame(196, $result['xxl']);
        self::assertSame(351, $result['xs']);
    }

    #[Test]
    public function calculateImageWidthsIncludesHiDpiVariantsWhenEnabled(): void
    {
        $result = $this->subject->calculateImageWidths($this->defaultSettings);

        self::assertArrayHasKey('sm@2x', $result);
        self::assertArrayHasKey('md@2x', $result);
        self::assertArrayHasKey('lg@2x', $result);
        self::assertArrayHasKey('xl@2x', $result);
        self::assertArrayHasKey('xxl@2x', $result);
        self::assertArrayHasKey('xs@2x', $result);

        self::assertSame(492, $result['sm@2x']);
        self::assertSame(432, $result['md@2x']);
        self::assertSame(432, $result['lg@2x']);
        self::assertSame(408, $result['xl@2x']);
        self::assertSame(392, $result['xxl@2x']);
        self::assertSame(702, $result['xs@2x']);
    }

    #[Test]
    public function calculateImageWidthsOmitsHiDpiVariantsWhenDisabled(): void
    {
        $settings = $this->defaultSettings;
        $settings['otGallery']['processing']['hiDpi'] = false;

        $result = $this->subject->calculateImageWidths($settings);

        self::assertArrayNotHasKey('sm@2x', $result);
        self::assertArrayNotHasKey('xs@2x', $result);
    }

    #[Test]
    public function calculateImageWidthsSkipsBreakpointWhenMaxWidthIsZero(): void
    {
        $settings = $this->defaultSettings;
        $settings['otGallery']['grid']['container']['md']['maxWidth'] = 0;

        $result = $this->subject->calculateImageWidths($settings);

        self::assertArrayNotHasKey('md', $result);
        self::assertArrayNotHasKey('md@2x', $result);
        // Other breakpoints remain
        self::assertArrayHasKey('sm', $result);
        self::assertArrayHasKey('lg', $result);
    }

    #[Test]
    public function calculateImageWidthsUsesFlexFormColumnOverrideWhenSet(): void
    {
        // Override md to 6 columns instead of default 3
        $record = [
            'tx_otgallery_columns_override' => 1,
            'tx_otgallery_columns_xs'  => 1,
            'tx_otgallery_columns_sm'  => 2,
            'tx_otgallery_columns_md'  => 6,
            'tx_otgallery_columns_lg'  => 4,
            'tx_otgallery_columns_xl'  => 5,
            'tx_otgallery_columns_xxl' => 6,
        ];

        $result = $this->subject->calculateImageWidths($this->defaultSettings, $record);

        // md with 6 columns: ceil((720-24 - (6-1)*24) / 6) = ceil((696-120)/6) = ceil(576/6) = 96
        self::assertSame(96, $result['md']);
    }

    #[Test]
    public function calculateImageWidthsIgnoresFlexFormOverrideWhenFlagIsZero(): void
    {
        $record = [
            'tx_otgallery_columns_override' => 0,
            'tx_otgallery_columns_md' => 6,
        ];

        $result = $this->subject->calculateImageWidths($this->defaultSettings, $record);

        // Without override flag, default 3 columns must be used
        self::assertSame(216, $result['md']);
    }

    #[Test]
    public function calculateImageWidthsUsesDefaultsWhenSettingsAreEmpty(): void
    {
        // Without container maxWidth settings all breakpoints should be skipped
        // (maxWidth defaults to 0 → continue), only xs is always produced
        $result = $this->subject->calculateImageWidths([]);

        self::assertArrayHasKey('xs', $result);
        self::assertArrayNotHasKey('sm', $result);
        self::assertArrayNotHasKey('md', $result);
    }

    // =========================================================================
    // generateSizesAttribute
    // =========================================================================

    #[Test]
    public function generateSizesAttributeReturnsCorrectMediaQueryString(): void
    {
        $result = $this->subject->generateSizesAttribute($this->defaultSettings);

        // xxl: (min-width: 1400px) 196px
        // xl:  (min-width: 1200px) 204px
        // lg:  (min-width: 992px) 216px
        // md:  (min-width: 768px) 216px
        // sm:  (min-width: 576px) 246px
        // xs fallback: calc(100vw - 24px)
        $expected = '(min-width: 1400px) 196px, (min-width: 1200px) 204px, (min-width: 992px) 216px, '
            . '(min-width: 768px) 216px, (min-width: 576px) 246px, calc(100vw - 24px)';

        self::assertSame($expected, $result);
    }

    #[Test]
    public function generateSizesAttributeOmitsBreakpointWhenMaxWidthIsZero(): void
    {
        $settings = $this->defaultSettings;
        $settings['otGallery']['grid']['container']['lg']['maxWidth'] = 0;

        $result = $this->subject->generateSizesAttribute($settings);

        self::assertStringNotContainsString('992px', $result);
        self::assertStringContainsString('768px', $result);
    }

    #[Test]
    public function generateSizesAttributeAlwaysEndsWithXsFallback(): void
    {
        $result = $this->subject->generateSizesAttribute($this->defaultSettings);

        self::assertStringEndsWith('calc(100vw - 24px)', $result);
    }

    // =========================================================================
    // generateRowColsClasses
    // =========================================================================

    #[Test]
    public function generateRowColsClassesReturnsCorrectBootstrapClasses(): void
    {
        $result = $this->subject->generateRowColsClasses($this->defaultSettings);

        self::assertSame(
            'row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 row-cols-xxl-6',
            $result
        );
    }

    #[Test]
    public function generateRowColsClassesRespectsFlexFormColumnOverride(): void
    {
        $record = [
            'tx_otgallery_columns_override' => 1,
            'tx_otgallery_columns_xs'  => 1,
            'tx_otgallery_columns_sm'  => 1,
            'tx_otgallery_columns_md'  => 2,
            'tx_otgallery_columns_lg'  => 2,
            'tx_otgallery_columns_xl'  => 3,
            'tx_otgallery_columns_xxl' => 3,
        ];

        $result = $this->subject->generateRowColsClasses($this->defaultSettings, $record);

        self::assertSame(
            'row-cols-1 row-cols-sm-1 row-cols-md-2 row-cols-lg-2 row-cols-xl-3 row-cols-xxl-3',
            $result
        );
    }

    #[Test]
    public function generateRowColsClassesDefaultsToOneColumnWhenSettingsAreMissing(): void
    {
        $result = $this->subject->generateRowColsClasses([]);

        self::assertSame(
            'row-cols-1 row-cols-sm-1 row-cols-md-1 row-cols-lg-1 row-cols-xl-1 row-cols-xxl-1',
            $result
        );
    }

    // =========================================================================
    // calculateConfigHash
    // =========================================================================

    #[Test]
    public function calculateConfigHashReturnsMd5String(): void
    {
        $hash = $this->subject->calculateConfigHash($this->defaultSettings);

        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $hash);
    }

    #[Test]
    public function calculateConfigHashIsDeterministic(): void
    {
        $hashFirst  = $this->subject->calculateConfigHash($this->defaultSettings);
        $hashSecond = $this->subject->calculateConfigHash($this->defaultSettings);

        self::assertSame($hashFirst, $hashSecond);
    }

    #[Test]
    public function calculateConfigHashChangesWhenColumnCountChanges(): void
    {
        $modifiedSettings = $this->defaultSettings;
        $modifiedSettings['otGallery']['grid']['columns']['lg'] = 999;

        $hashOriginal = $this->subject->calculateConfigHash($this->defaultSettings);
        $hashModified = $this->subject->calculateConfigHash($modifiedSettings);

        self::assertNotSame($hashOriginal, $hashModified);
    }

    #[Test]
    public function calculateConfigHashChangesWhenGutterChanges(): void
    {
        $modifiedSettings = $this->defaultSettings;
        $modifiedSettings['otGallery']['grid']['gutter'] = 48;

        $hashOriginal = $this->subject->calculateConfigHash($this->defaultSettings);
        $hashModified = $this->subject->calculateConfigHash($modifiedSettings);

        self::assertNotSame($hashOriginal, $hashModified);
    }

    #[Test]
    public function calculateConfigHashChangesWhenAspectRatioRecordValueChanges(): void
    {
        $recordFree  = ['tx_otgallery_aspect_ratio' => 'free',  'tx_otgallery_rendering' => 'cover'];
        $recordFixed = ['tx_otgallery_aspect_ratio' => '16:9', 'tx_otgallery_rendering' => 'cover'];

        $hashFree  = $this->subject->calculateConfigHash($this->defaultSettings, $recordFree);
        $hashFixed = $this->subject->calculateConfigHash($this->defaultSettings, $recordFixed);

        self::assertNotSame($hashFree, $hashFixed);
    }

    #[Test]
    public function calculateConfigHashChangesWhenHiDpiChanges(): void
    {
        $settingsWithHiDpi    = $this->defaultSettings;
        $settingsWithoutHiDpi = $this->defaultSettings;
        $settingsWithoutHiDpi['otGallery']['processing']['hiDpi'] = false;

        $hashWith    = $this->subject->calculateConfigHash($settingsWithHiDpi);
        $hashWithout = $this->subject->calculateConfigHash($settingsWithoutHiDpi);

        self::assertNotSame($hashWith, $hashWithout);
    }

    // =========================================================================
    // collectUniqueWidths
    // =========================================================================

    #[Test]
    public function collectUniqueWidthsReturnsSortedUniqueValues(): void
    {
        $widths = [
            'xs'   => 351,
            'sm'   => 246,
            'md'   => 216,
            'lg'   => 216,
            'xl'   => 204,
            'xxl'  => 196,
        ];

        $result = $this->subject->collectUniqueWidths($widths);

        // md and lg are identical (216) → one dropped
        // xl (204) is within 4.1% of xxl (196) → also dropped as near-duplicate
        // result sorted ascending
        self::assertSame([196, 216, 246, 351], $result);
    }

    #[Test]
    public function collectUniqueWidthsDeduplicatesHiDpiVariants(): void
    {
        $widths = [
            'xs'    => 351,
            'xs@2x' => 702,
            'sm'    => 246,
            'sm@2x' => 492,
        ];

        $result = $this->subject->collectUniqueWidths($widths);

        // All four values are distinct enough → all four kept
        self::assertSame([246, 351, 492, 702], $result);
    }

    #[Test]
    public function collectUniqueWidthsDropsNearDuplicatesWithinFivePercent(): void
    {
        // 200 and 209 differ by 4.5% → 209 is a near-duplicate of 200 and must be dropped
        $widths = ['a' => 200, 'b' => 209, 'c' => 400];

        $result = $this->subject->collectUniqueWidths($widths);

        self::assertSame([200, 400], $result);
    }

    #[Test]
    public function collectUniqueWidthsKeepsValuesThatExceedFivePercentThreshold(): void
    {
        // 200 and 212 differ by 6% → both are kept
        $widths = ['a' => 200, 'b' => 212];

        $result = $this->subject->collectUniqueWidths($widths);

        self::assertSame([200, 212], $result);
    }

    #[Test]
    public function collectUniqueWidthsFiltersOutZeroAndNegativeValues(): void
    {
        $widths = ['a' => 0, 'b' => 200, 'c' => -50, 'd' => 400];

        $result = $this->subject->collectUniqueWidths($widths);

        self::assertSame([200, 400], $result);
    }

    #[Test]
    public function collectUniqueWidthsReturnsEmptyArrayForEmptyInput(): void
    {
        $result = $this->subject->collectUniqueWidths([]);

        self::assertSame([], $result);
    }

    // =========================================================================
    // Edge cases shared across methods
    // =========================================================================

    #[Test]
    public function columnCountIsAtLeastOneWhenSettingIsZeroOrMissing(): void
    {
        $settings = $this->defaultSettings;
        $settings['otGallery']['grid']['columns']['sm'] = 0;

        $classes = $this->subject->generateRowColsClasses($settings);

        // max(1, 0) must produce row-cols-sm-1, not row-cols-sm-0
        self::assertStringContainsString('row-cols-sm-1', $classes);
    }

    #[Test]
    public function calculateImageWidthsWithSingleColumnProducesFullContainerWidth(): void
    {
        $settings = $this->defaultSettings;
        $settings['otGallery']['grid']['columns']['sm'] = 1;

        $result = $this->subject->calculateImageWidths($settings);

        // 1 column: ceil((540-24 - (1-1)*24) / 1) = ceil(516) = 516
        self::assertSame(516, $result['sm']);
    }
}