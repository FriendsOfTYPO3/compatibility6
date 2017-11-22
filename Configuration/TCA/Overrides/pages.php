<?php
defined('TYPO3_MODE') or die();

// Add "pages.storage_pid" field to TCA column
$additionalColumns = [
    'storage_pid' => [
        'exclude' => 1,
        'label' => 'LLL:EXT:compatibility6/Resources/Private/Language/locallang.xlf:storage_pid',
        'config' => [
            'type' => 'group',
            'internal_type' => 'db',
            'allowed' => 'pages',
            'size' => '1',
            'maxitems' => '1',
            'minitems' => '0',
            'show_thumbs' => '1',
            'wizards' => [
                'suggest' => [
                    'type' => 'suggest'
                ]
            ]
        ]
    ]
];
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('pages', $additionalColumns);

// Add palette
$GLOBALS['TCA']['pages']['palettes']['storage'] = [
    'showitem' => 'storage_pid;LLL:EXT:compatibility6/Resources/Private/Language/locallang.xlf:pages.storage_pid_formlabel',
    'canNotCollapse' => 1
];

// Add to "normal" pages, "external URL", "shortcut page" and "storage PID"
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'pages',
    '--palette--;LLL:EXT:compatibility6/Resources/Private/Language/locallang.xlf:pages.palettes.storage;storage',
    \TYPO3\CMS\Frontend\Page\PageRepository::DOKTYPE_DEFAULT . ','
    . \TYPO3\CMS\Frontend\Page\PageRepository::DOKTYPE_LINK . ','
    . \TYPO3\CMS\Frontend\Page\PageRepository::DOKTYPE_SHORTCUT . ','
    . \TYPO3\CMS\Frontend\Page\PageRepository::DOKTYPE_SYSFOLDER,
    'after:media'
);
