<?php
$EM_CONF[$_EXTKEY] = array(
    'title' => 'Compatibility Mode for TYPO3 CMS 6.x',
    'description' => 'Provides an additional backwards-compatibility layer with legacy functionality for sites that haven\'t fully migrated to v7 yet.',
    'category' => 'be',
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'author' => 'TYPO3 CMS Team',
    'author_email' => '',
    'author_company' => '',
    'version' => '7.6.5',
    'constraints' => array(
        'depends' => array(
            'typo3' => '7.6.0-7.6.99',
        ),
        'conflicts' => array(),
        'suggests' => array(),
    ),
    'autoload' => array(
        'psr-4' => array(
            'TYPO3\\CMS\\Compatibility6\\' => 'Classes',
        ),
    ),
);
