<?php

require_once __DIR__.'/src/autoload.php';

use Goutte\Compiler;

$compiler = new Compiler();
$compiler->compile();
