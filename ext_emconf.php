<?php

$EM_CONF['ot_gallery'] = [
    'title' => 'Gallery',
    'description' => 'Gallery extension with pre-processing, srcset, lightbox, metadata and pagination for TYPO3 v13.',
    'category' => 'plugin',
    'author' => 'Oliver Thiele',
    'author_email' => 'mail@oliver-thiele.de',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
