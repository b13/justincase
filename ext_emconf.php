<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Just In Case - Case-insensitive URLs',
    'description' => 'With incoming URLs, it does not matter if they are upper/lowercase, they just work.',
    'category' => 'fe',
    'state' => 'stable',
    'author' => 'b13',
    'author_email' => 'typo3@b13.com',
    'author_company' => 'b13 GmbH',
    'version' => '3.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
