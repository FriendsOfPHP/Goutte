<?php

require_once __DIR__.'/vendor/symfony/src/Symfony/Component/HttpFoundation/UniversalClassLoader.php';

use Symfony\Component\HttpFoundation\UniversalClassLoader;

$loader = new UniversalClassLoader();
$loader->registerNamespaces(array(
    'Symfony' => __DIR__.'/vendor/symfony/src',
    'Zend'    => __DIR__.'/vendor/zend/library',
    'Goutte'  => __DIR__,
));
$loader->register();
