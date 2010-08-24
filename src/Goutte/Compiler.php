<?php

namespace Goutte;

use Symfony\Component\Finder\Finder;
use Symfony\Framework\Kernel;

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
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Compiler
{
  public function compile($pharFile = 'goutte.phar')
  {
    if (file_exists($pharFile))
    {
      unlink($pharFile);
    }

    $phar = new \Phar($pharFile, 0, 'Goutte');
    $phar->setSignatureAlgorithm(\Phar::SHA1);

    $phar->startBuffering();

    // CLI Component files
    foreach ($this->getFiles() as $file)
    {
      $path = str_replace(__DIR__.'/', '', $file);
      $content = Kernel::stripComments(file_get_contents($file));
      $content = preg_replace("#require_once 'Zend/.*?';#", '', $content);

      $phar->addFromString($path, $content);
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
    return "<?php ".$this->getLicense()." require_once __DIR__.'/src/autoload.php'; __HALT_COMPILER();";
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
     * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
     *
     * This source file is subject to the MIT license that is bundled
     * with this source code in the file LICENSE.
     */';
  }

  protected function getFiles()
  {
    $files = array(
      'LICENSE',
      'src/autoload.php',
      'src/vendor/symfony/src/Symfony/Framework/UniversalClassLoader.php',
      'src/vendor/zend/library/Zend/Exception.php',
      //'src/vendor/zend/library/Zend/Date.php',
      'src/vendor/zend/library/Zend/Uri/Uri.php',
      'src/vendor/zend/library/Zend/Validator/Validator.php',
      'src/vendor/zend/library/Zend/Validator/AbstractValidator.php',
      'src/vendor/zend/library/Zend/Validator/Hostname.php',
      'src/vendor/zend/library/Zend/Validator/Ip.php',
      //'src/vendor/zend/library/Zend/Validator/Hostname/Biz.php',
      //'src/vendor/zend/library/Zend/Validator/Hostname/Cn.php',
      'src/vendor/zend/library/Zend/Validator/Hostname/Com.php',
      'src/vendor/zend/library/Zend/Validator/Hostname/Jp.php',
    );

    $dirs = array(
      'src/Goutte',
      'src/vendor/symfony/src/Symfony/Component/BrowserKit',
      'src/vendor/symfony/src/Symfony/Component/DomCrawler',
      'src/vendor/symfony/src/Symfony/Component/CssSelector',
      'src/vendor/symfony/src/Symfony/Component/Process',
      //'src/vendor/zend/library/Zend/Date',
      'src/vendor/zend/library/Zend/Uri',
      'src/vendor/zend/library/Zend/Http',
    );

    $finder = new Finder();
    $iterator = $finder->files()->name('*.php')->in($dirs);

    return array_merge($files, iterator_to_array($iterator));
  }
}
