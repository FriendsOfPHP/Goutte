<?php

require_once __DIR__.'/vendor/symfony/src/Symfony/Foundation/UniversalClassLoader.php';

use Symfony\Foundation\UniversalClassLoader;

$loader = new UniversalClassLoader();
$loader->registerNamespaces(array(
    'Symfony' => __DIR__.'/vendor/symfony/src',
    'Zend'    => __DIR__.'/vendor/zend/library',
    'Goutte'  => __DIR__,
));
$loader->register();
