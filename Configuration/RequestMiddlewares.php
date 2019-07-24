<?php

return [
    'frontend' => [
        'b13/just-in-case' => [
            'target' => \B13\JustInCase\Middleware\LowerCaseUri::class,
            'after' => [
                'typo3/cms-frontend/site-resolver'
            ],
            'before' => [
                'typo3/cms-frontend/base-redirect-resolver'
            ],
        ]
    ]
];
