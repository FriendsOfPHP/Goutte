<?php

require_once __DIR__.'/vendor/symfony/src/Symfony/Component/ClassLoader/UniversalClassLoader.php';

use Symfony\Component\ClassLoader\UniversalClassLoader;

$loader = new UniversalClassLoader();
$loader->registerNamespaces(array(
    'Symfony' => __DIR__.'/vendor/symfony/src',
    'Zend'    => __DIR__.'/vendor/zend/library',
    'Goutte'  => __DIR__,
));
$loader->register();
