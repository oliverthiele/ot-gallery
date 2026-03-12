<?php

declare(strict_types=1);

namespace OliverThiele\OtGallery\DataProcessing;

use OliverThiele\OtGallery\Service\ImageSizeCalculatorService;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Resource\AbstractFile;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Resource\FileType;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

final class GalleryProcessor implements DataProcessorInterface
{
    public function __construct(
        private readonly ResourceFactory $resourceFactory,
        private readonly FileRepository $fileRepository,
        private readonly ImageSizeCalculatorService $imageSizeCalculatorService,
        private readonly FlexFormService $flexFormService,
    ) {
    }

    /**
     * @param array<string, mixed> $contentObjectConfiguration
     * @param array<string, mixed> $processorConfiguration
     * @param array<string, mixed> $processedData
     * @return array<string, mixed>
     */
    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData
    ): array {
        $record = $processedData['data'];

        // SiteSet settings from the current site — NOT TypoScript settings
        $siteSettings = $this->getSiteSettings($cObj);

        // Parse FlexForm data — settings.* keys become the config for this CE
        $flexSettings = $this->parseFlexForm($record['pi_flexform'] ?? '');

        $source = $record['tx_otgallery_source'] ?? 'files';
        $files = $source === 'folder'
            ? $this->getFilesFromFolder($record)
            : $this->getFilesFromFal($record);

        $files = $this->sortFiles($files, $flexSettings);

        $itemsPerPage = (int)($flexSettings['itemsPerPage'] ?: ($siteSettings['otGallery']['pagination']['itemsPerPage'] ?? 48));
        $currentPage = $this->getCurrentPage($cObj);

        $paginator = new ArrayPaginator($files, $currentPage, $itemsPerPage > 0 ? $itemsPerPage : PHP_INT_MAX);
        $pagination = new SimplePagination($paginator);

        // Pass FlexForm column overrides into the record-like array for the calculator
        $calculatorRecord = $this->buildCalculatorRecord($flexSettings);
        $sizesAttribute = $this->imageSizeCalculatorService->generateSizesAttribute($siteSettings, $calculatorRecord);
        $imageWidths = $this->imageSizeCalculatorService->calculateImageWidths($siteSettings, $calculatorRecord);

        $rowColsClasses = $this->imageSizeCalculatorService->generateRowColsClasses($siteSettings, $calculatorRecord);
        $aspectRatio = $flexSettings['aspectRatio'] ?? 'free';
        $aspectRatioCss = $this->formatAspectRatioCss($aspectRatio);
        $lightboxCaptionFields = $this->buildLightboxCaptionFields($siteSettings);

        // Masonry requires variable image heights — fall back to grid when a fixed ratio is set
        $layout = $flexSettings['layout'] ?? 'grid';
        $effectiveLayout = ($layout === 'masonry' && $aspectRatio !== 'free') ? 'grid' : $layout;

        $targetVariableName = $processorConfiguration['as'] ?? 'gallery';
        $processedData[$targetVariableName] = [
            'images' => $paginator->getPaginatedItems(),
            'allImages' => $files,
            'paginator' => $paginator,
            'pagination' => $pagination,
            'sizesAttribute' => $sizesAttribute,
            'imageWidths' => $imageWidths,
            'rowColsClasses' => $rowColsClasses,
            'aspectRatioCss' => $aspectRatioCss,
            'effectiveLayout' => $effectiveLayout,
            'totalCount' => count($files),
            'source' => $source,
            'flex' => $flexSettings,
            'lightboxCaptionFields' => $lightboxCaptionFields,
        ];

        return $processedData;
    }

    /**
     * Reads SiteSet settings from the current site object.
     * These are the settings defined in settings.definitions.yaml, NOT TypoScript settings.
     *
     * @return array<string, mixed>
     */
    private function getSiteSettings(ContentObjectRenderer $cObj): array
    {
        $request = $cObj->getRequest();
        $site = $request->getAttribute('site');

        if (!$site instanceof Site) {
            return [];
        }

        return $site->getSettings()->getAll();
    }

    /**
     * Parses pi_flexform XML and returns a flat array of the settings.* values.
     *
     * @return array<string, mixed>
     */
    private function parseFlexForm(string $flexFormXml): array
    {
        if (empty($flexFormXml)) {
            return [];
        }

        $data = $this->flexFormService->convertFlexFormContentToArray($flexFormXml);

        // FlexFormService returns ['settings' => [...]] — we flatten one level
        return $data['settings'] ?? [];
    }

    /**
     * Builds a record-compatible array for ImageSizeCalculatorService from FlexForm settings.
     *
     * @param array<string, mixed> $flexSettings
     * @return array<string, mixed>
     */
    private function buildCalculatorRecord(array $flexSettings): array
    {
        return [
            'tx_otgallery_columns_override' => (int)($flexSettings['columnsOverride'] ?? 0),
            'tx_otgallery_columns_xs' => (int)($flexSettings['columnsXs'] ?? 0),
            'tx_otgallery_columns_sm' => (int)($flexSettings['columnsSm'] ?? 0),
            'tx_otgallery_columns_md' => (int)($flexSettings['columnsMd'] ?? 0),
            'tx_otgallery_columns_lg' => (int)($flexSettings['columnsLg'] ?? 0),
            'tx_otgallery_columns_xl' => (int)($flexSettings['columnsXl'] ?? 0),
            'tx_otgallery_columns_xxl' => (int)($flexSettings['columnsXxl'] ?? 0),
            'tx_otgallery_aspect_ratio' => $flexSettings['aspectRatio'] ?? 'free',
            'tx_otgallery_rendering' => $flexSettings['rendering'] ?? 'cover',
        ];
    }

    /**
     * @param array<string, mixed> $record
     * @return FileInterface[]
     */
    private function getFilesFromFolder(array $record): array
    {
        $folderIdentifiers = array_filter(
            array_map('trim', explode(',', $record['tx_otgallery_folder'] ?? ''))
        );

        if (empty($folderIdentifiers)) {
            return [];
        }

        $recursiveDepth = (int)($record['recursive'] ?? 0);
        $files = [];

        foreach ($folderIdentifiers as $identifier) {
            try {
                $folder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($identifier);
                $files = array_merge($files, $this->getFilesFromFolderWithDepth($folder, $recursiveDepth));
            } catch (ResourceDoesNotExistException) {
                // Skip non-existing folders
            }
        }

        return $files;
    }

    /**
     * Recursively collects image files from a folder up to the given depth.
     * Depth 0 = current folder only, 250 = all levels (TYPO3 convention).
     * Non-image files (videos, documents, etc.) are filtered out.
     *
     * @return FileInterface[]
     */
    private function getFilesFromFolderWithDepth(Folder $folder, int $depth): array
    {
        if ($depth >= 250) {
            $files = $folder->getFiles(0, 0, Folder::FILTER_MODE_USE_OWN_AND_STORAGE_FILTERS, true);
        } else {
            $files = $folder->getFiles(0, 0, Folder::FILTER_MODE_USE_OWN_AND_STORAGE_FILTERS, false);

            if ($depth > 0) {
                foreach ($folder->getSubfolders() as $subfolder) {
                    $files = array_merge($files, $this->getFilesFromFolderWithDepth($subfolder, $depth - 1));
                }
            }
        }

        return array_values(
            array_filter(
                $files,
                static fn(FileInterface $file) => $file instanceof AbstractFile && $file->getType(
                ) === FileType::IMAGE->value
            )
        );
    }

    /**
     * Returns FileReference objects so that per-reference metadata (title, description,
     * alternative, copyright set on the content element) takes precedence over the
     * global sys_file metadata.
     *
     * @param array<string, mixed> $record
     * @return FileInterface[]
     */
    private function getFilesFromFal(array $record): array
    {
        return $this->fileRepository->findByRelation(
            'tt_content',
            'assets',
            (int)$record['uid']
        );
    }

    /**
     * @param FileInterface[] $files
     * @param array<string, mixed> $flexSettings
     * @return FileInterface[]
     */
    private function sortFiles(array $files, array $flexSettings): array
    {
        $sortField = $flexSettings['sortField'] ?? 'name';
        $sortDirection = strtolower($flexSettings['sortDirection'] ?? 'asc');

        if ($sortField === 'custom') {
            return $files;
        }

        usort($files, static function (FileInterface $a, FileInterface $b) use ($sortField): int {
            return match ($sortField) {
                'date' => $a->getProperty('creation_date') <=> $b->getProperty('creation_date'),
                default => strnatcasecmp($a->getName(), $b->getName()),
            };
        });

        if ($sortDirection === 'desc') {
            $files = array_reverse($files);
        }

        return $files;
    }

    private function getCurrentPage(ContentObjectRenderer $cObj): int
    {
        $params = $cObj->getRequest()->getQueryParams();
        return max(1, (int)($params['tx_otgallery_page'] ?? 1));
    }

    /**
     * Builds an associative array of enabled lightbox caption fields from the SiteSet setting
     * otGallery.lightbox.captionFields (comma-separated: title,description,copyright).
     *
     * @param array<string, mixed> $siteSettings
     * @return array<string, bool>
     */
    private function buildLightboxCaptionFields(array $siteSettings): array
    {
        $configuredFieldsString = (string)($siteSettings['otGallery']['lightbox']['captionFields'] ?? 'title,description,copyright');
        $configuredFieldNames = array_filter(array_map('trim', explode(',', $configuredFieldsString)));

        return [
            'title' => in_array('title', $configuredFieldNames, true),
            'description' => in_array('description', $configuredFieldNames, true),
            'copyright' => in_array('copyright', $configuredFieldNames, true),
        ];
    }

    /**
     * Converts a FlexForm aspect ratio value (e.g. "4:3") to CSS format ("4/3").
     * Returns empty string for "free" (no fixed ratio).
     */
    private function formatAspectRatioCss(string $ratio): string
    {
        if ($ratio === 'free' || $ratio === '') {
            return '';
        }

        return str_replace(':', '/', $ratio);
    }
}
