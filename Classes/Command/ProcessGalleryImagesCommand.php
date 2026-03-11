<?php

declare(strict_types=1);

namespace OliverThiele\OtGallery\Command;

use OliverThiele\OtGallery\Service\ImageSizeCalculatorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Site\SiteFinder;

#[AsCommand(
    name: 'gallery:process',
    description: 'Pre-processes gallery images into all required sizes and formats.'
)]
final class ProcessGalleryImagesCommand extends Command
{
    public function __construct(
        private readonly ImageSizeCalculatorService $imageSizeCalculatorService,
        private readonly ResourceFactory $resourceFactory,
        private readonly FileRepository $fileRepository,
        private readonly FlexFormService $flexFormService,
        private readonly SiteFinder $siteFinder,
        private readonly ConnectionPool $connectionPool,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('content-uid', null, InputOption::VALUE_OPTIONAL, 'Process only a specific content element UID')
            ->addOption('unprocessed-only', null, InputOption::VALUE_NONE, 'Skip content elements whose config hash is unchanged')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be processed without actually doing it');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Gallery Image Pre-Processor');

        $contentUid = $input->getOption('content-uid');
        $unprocessedOnly = (bool)$input->getOption('unprocessed-only');
        $dryRun = (bool)$input->getOption('dry-run');

        if ($dryRun) {
            $io->warning('DRY RUN — no files will be created or updated.');
        }

        $records = $this->loadGalleryRecords($contentUid ? (int)$contentUid : null);

        if (empty($records)) {
            $io->info('No gallery content elements found.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d gallery content element(s).', count($records)));

        $totalProcessed = 0;
        $totalSkipped = 0;
        $totalErrors = 0;

        foreach ($records as $record) {
            $settings = $this->getSiteSettings((int)$record['pid']);
            $calculatorRecord = $this->buildCalculatorRecord($record);
            $newHash = $this->imageSizeCalculatorService->calculateConfigHash($settings, $calculatorRecord);

            if ($unprocessedOnly && $record['tx_otgallery_config_hash'] === $newHash) {
                $io->writeln(sprintf(
                    '  <info>SKIP</info> CE #%d "%s" (up to date)',
                    $record['uid'],
                    $record['header'] ?: '(no title)'
                ));
                $totalSkipped++;
                continue;
            }

            $files = $this->getFilesForRecord($record);
            $imageWidths = $this->imageSizeCalculatorService->calculateImageWidths($settings, $calculatorRecord);

            $io->writeln(sprintf(
                '  <comment>PROC</comment> CE #%d "%s" — %d images × %d sizes',
                $record['uid'],
                $record['header'] ?: '(no title)',
                count($files),
                count($imageWidths)
            ));

            if ($io->isVerbose()) {
                $uniqueWidths = $this->collectUniqueWidths($imageWidths);
                $io->writeln(sprintf(
                    '         Widths (px): %s',
                    implode(', ', $uniqueWidths)
                ));
                foreach (['xs', 'sm', 'md', 'lg', 'xl', 'xxl'] as $breakpoint) {
                    $width = $imageWidths[$breakpoint] ?? null;
                    if ($width !== null) {
                        [$columnCount, $source] = $this->getColumnInfo($breakpoint, $settings, $calculatorRecord);
                        $hiDpi = isset($imageWidths[$breakpoint . '@2x']) ? ', @2x: ' . $imageWidths[$breakpoint . '@2x'] . 'px' : '';
                        $io->writeln(sprintf(
                            '         %s: %d cols (%s) → %dpx%s',
                            str_pad($breakpoint, 4),
                            $columnCount,
                            $source,
                            $width,
                            $hiDpi
                        ));
                    }
                }
            }

            if (!$dryRun) {
                $errors = $this->processFiles($files, $imageWidths, $io);
                $totalErrors += $errors;
                $this->updateProcessingStatus($record['uid'], $newHash);
            }

            $totalProcessed++;
        }

        $io->newLine();
        $io->success(sprintf(
            'Done. Processed: %d | Skipped: %d | Errors: %d',
            $totalProcessed,
            $totalSkipped,
            $totalErrors
        ));

        return $totalErrors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Reads SiteSet settings for the given page ID.
     * Falls back to an empty array if no site is found (CLI edge case).
     *
     * @return array<string, mixed>
     */
    private function getSiteSettings(int $pid): array
    {
        try {
            $site = $this->siteFinder->getSiteByPageId($pid);
            return $site->getSettings()->getAll();
        } catch (SiteNotFoundException) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadGalleryRecords(?int $uid): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $qb->select(
            'uid',
            'pid',
            'header',
            'pi_flexform',
            'tx_otgallery_source',
            'tx_otgallery_folder',
            'tx_otgallery_folder_recursive',
            'tx_otgallery_images',
            'tx_otgallery_config_hash',
            'tx_otgallery_processed_at'
        )
            ->from('tt_content')
            ->where($qb->expr()->eq('CType', $qb->createNamedParameter('ot_gallery')))
            ->andWhere($qb->expr()->eq('deleted', 0))
            ->andWhere($qb->expr()->eq('hidden', 0));

        if ($uid !== null) {
            $qb->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter($uid, \Doctrine\DBAL\ParameterType::INTEGER)));
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Parses pi_flexform and builds a record-compatible array for ImageSizeCalculatorService.
     *
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function buildCalculatorRecord(array $record): array
    {
        $flexSettings = [];
        if (!empty($record['pi_flexform'])) {
            $data = $this->flexFormService->convertFlexFormContentToArray((string)$record['pi_flexform']);
            $flexSettings = $data['settings'] ?? [];
        }

        return [
            'tx_otgallery_columns_override' => (int)($flexSettings['columnsOverride'] ?? 0),
            'tx_otgallery_columns_xs'       => (int)($flexSettings['columnsXs'] ?? 0),
            'tx_otgallery_columns_sm'       => (int)($flexSettings['columnsSm'] ?? 0),
            'tx_otgallery_columns_md'       => (int)($flexSettings['columnsMd'] ?? 0),
            'tx_otgallery_columns_lg'       => (int)($flexSettings['columnsLg'] ?? 0),
            'tx_otgallery_columns_xl'       => (int)($flexSettings['columnsXl'] ?? 0),
            'tx_otgallery_columns_xxl'      => (int)($flexSettings['columnsXxl'] ?? 0),
            'tx_otgallery_aspect_ratio'     => $flexSettings['aspectRatio'] ?? 'free',
            'tx_otgallery_rendering'        => $flexSettings['rendering'] ?? 'cover',
        ];
    }

    /**
     * @param array<string, mixed> $record
     * @return File[]
     */
    private function getFilesForRecord(array $record): array
    {
        if (($record['tx_otgallery_source'] ?? 'files') === 'folder') {
            $folderIdentifiers = array_filter(
                array_map('trim', explode(',', (string)($record['tx_otgallery_folder'] ?? '')))
            );
            if (empty($folderIdentifiers)) {
                return [];
            }
            $recursive = (bool)($record['tx_otgallery_folder_recursive'] ?? false);
            $files = [];
            foreach ($folderIdentifiers as $identifier) {
                try {
                    $folder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($identifier);
                    $files = array_merge($files, $folder->getFiles(0, 0, \TYPO3\CMS\Core\Resource\Folder::FILTER_MODE_USE_OWN_AND_STORAGE_FILTERS, $recursive));
                } catch (\Exception) {
                    // Skip non-existing or inaccessible folders
                }
            }
            return $files;
        }

        $refs = $this->fileRepository->findByRelation('tt_content', 'tx_otgallery_images', (int)$record['uid']);
        return array_map(static fn($ref) => $ref->getOriginalFile(), $refs);
    }

    /**
     * Processes files using the same instructions as GallerySrcsetViewHelper
     * to ensure cache hits on the first frontend page load.
     *
     * @param File[] $files
     * @param array<string, int> $imageWidths
     */
    private function processFiles(array $files, array $imageWidths, SymfonyStyle $io): int
    {
        $errors = 0;
        $uniqueWidths = $this->collectUniqueWidths($imageWidths);

        foreach ($files as $file) {
            foreach ($uniqueWidths as $width) {
                try {
                    $file->process(
                        \TYPO3\CMS\Core\Resource\ProcessedFile::CONTEXT_IMAGECROPSCALEMASK,
                        ['width' => $width, 'fileExtension' => 'webp']
                    );
                } catch (\Exception $e) {
                    $errors++;
                    $io->writeln(sprintf(
                        '    <error>ERROR</error> %s @ %dpx: %s',
                        $file->getName(),
                        $width,
                        $e->getMessage()
                    ));
                }
            }
        }

        return $errors;
    }

    /**
     * Returns the column count and its source (CE override or Site Settings) for a breakpoint.
     *
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $calculatorRecord
     * @return array{0: int, 1: string}
     */
    private function getColumnInfo(string $breakpoint, array $settings, array $calculatorRecord): array
    {
        if (!empty($calculatorRecord['tx_otgallery_columns_override'])) {
            $fieldName = 'tx_otgallery_columns_' . $breakpoint;
            $columnCount = (int)($calculatorRecord[$fieldName] ?? 0);
            if ($columnCount > 0) {
                return [$columnCount, 'CE override'];
            }
        }

        $columnCount = max(1, (int)($settings['otGallery']['grid']['columns'][$breakpoint] ?? 1));
        return [$columnCount, 'Site Settings'];
    }

    /**
     * Collects unique pixel widths, deduplicating values within 5% of each other.
     * Mirrors the logic in GallerySrcsetViewHelper::collectUniqueWidths().
     *
     * @param array<string, int> $widths
     * @return int[]
     */
    private function collectUniqueWidths(array $widths): array
    {
        $values = array_values(array_filter($widths, static fn($w) => (int)$w > 0));
        $values = array_map('intval', $values);
        sort($values);

        $result = [];
        foreach ($values as $width) {
            $isDuplicate = false;
            foreach ($result as $existing) {
                if ($existing > 0 && abs($width - $existing) / $existing < 0.05) {
                    $isDuplicate = true;
                    break;
                }
            }
            if (!$isDuplicate) {
                $result[] = $width;
            }
        }

        return $result;
    }

    private function updateProcessingStatus(int $uid, string $hash): void
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $qb->update('tt_content')
            ->set('tx_otgallery_config_hash', $hash)
            ->set('tx_otgallery_processed_at', time())
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid, \Doctrine\DBAL\ParameterType::INTEGER)))
            ->executeStatement();
    }
}
