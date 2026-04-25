<?php

$EM_CONF['ot_gallery'] = [
    'title' => 'Gallery',
    'description' => 'Gallery extension with pre-processing, srcset, lightbox, metadata and pagination for TYPO3 v13 and v14.',
    'category' => 'plugin',
    'author' => 'Oliver Thiele',
    'author_email' => 'mail@oliver-thiele.de',
    'state' => 'stable',
    'version' => '1.2.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
