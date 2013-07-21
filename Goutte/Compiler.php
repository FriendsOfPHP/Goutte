<?php

/*
 * This file is part of the Goutte utility.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Goutte;

use Symfony\Component\Finder\Finder;

/**
 * The Compiler class compiles the Goutte utility.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Compiler
{
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
            $path = str_replace(__DIR__.'/', '', $file);
            $phar->addFromString($path, file_get_contents($file));
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
        return "<?php ".$this->getLicense()." require_once __DIR__.'/vendor/autoload.php'; __HALT_COMPILER();";
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
            'vendor/autoload.php',
            'Goutte/Client.php',
            'vendor/guzzle/http/Guzzle/Http/Resources/cacert.pem',
            'vendor/guzzle/http/Guzzle/Http/Resources/cacert.pem.md5'
        );

        $dirs = array(
            'vendor/composer',
            'vendor/symfony',
            'vendor/guzzle'
        );

        $iterator = Finder::create()->files()->name('*.php')->in($dirs);

        return array_merge($files, iterator_to_array($iterator));
    }
}
