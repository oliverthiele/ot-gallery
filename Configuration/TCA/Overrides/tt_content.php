<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

(static function (): void {
    ExtensionManagementUtility::addPlugin(
        [
            'label' => 'LLL:EXT:ot_gallery/Resources/Private/Language/locallang_db.xlf:tx_otgallery_list.name',
            'value' => 'ot_gallery',
            'icon' => 'ot-gallery',
            'description' => 'LLL:EXT:ot_gallery/Resources/Private/Language/locallang_db.xlf:tx_otgallery_list.description',
            'group' => 'special',
        ],
        'CType',
        'ot_gallery'
    );

    $GLOBALS['TCA']['tt_content']['columns']['tx_otgallery_source'] = [
        'label' => 'LLL:EXT:ot_gallery/Resources/Private/Language/locallang_db.xlf:tt_content.tx_otgallery_source',
        'onChange' => 'reload',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' => [
                [
                    'label' => 'LLL:EXT:ot_gallery/Resources/Private/Language/locallang_db.xlf:tt_content.tx_otgallery_source.files',
                    'value' => 'files',
                ],
                [
                    'label' => 'LLL:EXT:ot_gallery/Resources/Private/Language/locallang_db.xlf:tt_content.tx_otgallery_source.folder',
                    'value' => 'folder',
                ],
            ],
            'default' => 'files',
        ],
    ];

    $GLOBALS['TCA']['tt_content']['columns']['tx_otgallery_folder'] = [
        'label' => 'LLL:EXT:ot_gallery/Resources/Private/Language/locallang_db.xlf:tt_content.tx_otgallery_folder',
        'config' => [
            'type' => 'folder',
        ],
    ];

    // Internal fields for CLI pre-processing (not shown in BE)
    $GLOBALS['TCA']['tt_content']['columns']['tx_otgallery_config_hash'] = [
        'config' => [
            'type' => 'passthrough',
        ],
    ];

    $GLOBALS['TCA']['tt_content']['columns']['tx_otgallery_processed_at'] = [
        'config' => [
            'type' => 'passthrough',
        ],
    ];

    ExtensionManagementUtility::addTCAcolumns('tt_content', $GLOBALS['TCA']['tt_content']['columns']);

    // Register FlexForm
    ExtensionManagementUtility::addPiFlexFormValue(
        '*',
        'FILE:EXT:ot_gallery/Configuration/FlexForms/FlexForm.xml',
        'ot_gallery'
    );

    $GLOBALS['TCA']['tt_content']['types']['ot_gallery'] = [
        'showitem' => '
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
                --palette--;;general,
                --palette--;;headers,
            --div--;LLL:EXT:ot_gallery/Resources/Private/Language/locallang_db.xlf:tab.images,
                tx_otgallery_source,
                assets,
                tx_otgallery_folder,
                recursive,
            --div--;LLL:EXT:ot_gallery/Resources/Private/Language/locallang_db.xlf:tt_content.tab.configuration,
                pi_flexform,
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
                --palette--;;hidden,
                --palette--;;access,
        ',
        // Override displayCond for shared fields without affecting other CTypes
        'columnsOverrides' => [
            'assets' => [
                'displayCond' => 'FIELD:tx_otgallery_source:=:files',
            ],
            'tx_otgallery_folder' => [
                'displayCond' => 'FIELD:tx_otgallery_source:=:folder',
            ],
            'recursive' => [
                'displayCond' => 'FIELD:tx_otgallery_source:=:folder',
            ],
        ],
    ];
})();
