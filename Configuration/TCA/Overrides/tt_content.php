<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

(static function (): void {
    ExtensionManagementUtility::addPlugin(
        [
            'label' => 'ot_gallery.db:tx_otgallery_list.name',
            'value' => 'ot_gallery',
            'icon' => 'ot-gallery',
            'description' => 'ot_gallery.db:tx_otgallery_list.description',
            'group' => 'special',
        ],
        'CType',
        'ot_gallery'
    );

    $GLOBALS['TCA']['tt_content']['columns']['tx_otgallery_source'] = [
        'label' => 'ot_gallery.db:tt_content.tx_otgallery_source',
        'onChange' => 'reload',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' => [
                [
                    'label' => 'ot_gallery.db:tt_content.tx_otgallery_source.files',
                    'value' => 'files',
                ],
                [
                    'label' => 'ot_gallery.db:tt_content.tx_otgallery_source.folder',
                    'value' => 'folder',
                ],
            ],
            'default' => 'files',
        ],
    ];

    $GLOBALS['TCA']['tt_content']['columns']['tx_otgallery_folder'] = [
        'label' => 'ot_gallery.db:tt_content.tx_otgallery_folder',
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

    $GLOBALS['TCA']['tt_content']['types']['ot_gallery'] = [
        'showitem' => '
            --div--;core.form.tabs:general,
                --palette--;;general,
                --palette--;;headers,
            --div--;ot_gallery.db:tab.images,
                tx_otgallery_source,
                assets,
                tx_otgallery_folder,
                recursive,
            --div--;ot_gallery.db:tt_content.tab.configuration,
                pi_flexform,
            --div--;core.form.tabs:access,
                --palette--;;hidden,
                --palette--;;access,
        ',
        // Override displayCond for shared fields without affecting other CTypes
        'columnsOverrides' => [
            // Registers the FlexForm data structure. ExtensionManagementUtility::
            // addPiFlexFormValue() is deprecated since v14 and removed in v15.
            'pi_flexform' => [
                'config' => [
                    'ds' => 'FILE:EXT:ot_gallery/Configuration/FlexForms/FlexForm.xml',
                ],
            ],
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
