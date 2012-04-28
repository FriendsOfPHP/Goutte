<?php

namespace Goutte;

use Symfony\Component\Finder\Finder;

/*
 * This file is part of the Goutte utility.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * The Compiler class compiles the Goutte utility.
 *
 * @package    Goutte
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Compiler
{

    /**
     * Compile the phar file
     * 
     * @param type $pharFile Filename
     * 
     * @return void
     */
    public function compile($pharFile = 'goutte.phar')
    {
        if (file_exists($pharFile)) {
            unlink($pharFile);
        }

        $phar = new \Phar($pharFile, 0, 'Goutte');
        $phar->setSignatureAlgorithm(\Phar::SHA1);

        $phar->startBuffering();

        // CLI Component files
        foreach ($this->getFiles() as $file) {
            $path = str_replace(__DIR__ . '/', '', $file);
            $phar->addFromString($path, php_strip_whitespace($file));
        }

        // Stubs
        $phar['_cli_stub.php'] = $this->getCliStub();
        $phar['_web_stub.php'] = $this->getWebStub();
        $phar->setDefaultStub('_cli_stub.php', '_web_stub.php');

        $phar->stopBuffering();

        // $phar->compressFiles(\Phar::GZ);

        unset($phar);
    }

    protected function getCliStub()
    {
        return "<?php " . $this->getLicense() . " require_once __DIR__.'/autoload.php'; __HALT_COMPILER();";
    }

    protected function getWebStub()
    {
        return "<?php throw new \LogicException('This PHAR file can only be used from the CLI.'); __HALT_COMPILER();";
    }

    protected function getLicense()
    {
        return '
    /*
     * This file is part of the Goutte utility.
     *
     * (c) Fabien Potencier <fabien@symfony.com>
     *
     * This source file is subject to the MIT license that is bundled
     * with this source code in the file LICENSE.
     */';
    }

    protected function getFiles()
    {
        $files = array(
            'LICENSE',
            'autoload.php',
            'vendor/Symfony/Component/ClassLoader/UniversalClassLoader.php',
        );

        $dirs = array(
            'src/Goutte',
            'vendor/Symfony/Component/BrowserKit',
            'vendor/Symfony/Component/DomCrawler',
            'vendor/Symfony/Component/CssSelector',
            'vendor/Symfony/Component/Process',
            'vendor/Buzz/lib',
        );

        $finder = new Finder();
        $iterator = $finder->files()->name('*.php')->in($dirs);

        return array_merge($files, iterator_to_array($iterator));
    }

}

