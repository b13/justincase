<?php

$config = \TYPO3\CodingStandards\CsFixerConfig::create();
$config->getFinder()->exclude(['public', 'vendor'])->in(__DIR__);
return $config;
