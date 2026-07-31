<?php

$EM_CONF['ot_gallery'] = [
    'title' => 'Gallery',
    'description' => 'Gallery extension with pre-processing, srcset, lightbox, metadata and pagination for TYPO3 v13 and v14.',
    'category' => 'plugin',
    'author' => 'Oliver Thiele',
    'author_email' => 'mail@oliver-thiele.de',
    'state' => 'stable',
    'version' => '2.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '14.3.0-14.99.99',
            'php' => '8.4.0-8.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
