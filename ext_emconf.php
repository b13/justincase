<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Just In Case - Case-insensitive URLs',
    'description' => 'With incoming URLs, it does not matter if they are upper/lowercase, they just work.',
    'category' => 'fe',
    'state' => 'stable',
    'author' => 'b13',
    'author_email' => 'typo3@b13.com',
    'author_company' => 'b13 GmbH',
    'version' => '2.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.0-13.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
