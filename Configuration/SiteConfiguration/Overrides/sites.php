<?php

/**
 * Language specific justincase configuration
 */
$GLOBALS['SiteConfiguration']['site_language']['columns']['redirectOnUpperCase'] = [
    'label' => 'Redirect URL on upper-case to lower-case (Justincase)',
    'onChange' => 'reload',

    'config' => [
        'type' => 'check',
        'renderType' => 'checkboxToggle',
        'default' => 1,
        'items' => [
            [
                0 => '',
                1 => ''
            ]
        ]
    ]
];

$GLOBALS['SiteConfiguration']['site_language']['columns']['redirectStatusCode'] = [
    'label' => 'Redirect status code (Justincase)',
    'config' => [
        'type' => 'input',
        'default' => '307',
        'placeholder' => '307',
        'size' => 10
    ],
    'displayCond' => 'FIELD:redirectOnUpperCase:=:1'

];

$GLOBALS['SiteConfiguration']['site_language']['palettes']['justincase']['showitem'] = 'redirectOnUpperCase, redirectStatusCode';

$GLOBALS['SiteConfiguration']['site_language']['types']['1']['showitem'] = str_replace(
    'flag',
    'flag, ,--palette--;;justincase',
    $GLOBALS['SiteConfiguration']['site_language']['types']['1']['showitem']
);
