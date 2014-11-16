<?php

use Symfony\CS\Config\Config;

$config = new Config();

$config->getFinder()
    ->in(__DIR__)
    ->notPath('Goutte/Resources/phar-stub.php')
;

return $config;
