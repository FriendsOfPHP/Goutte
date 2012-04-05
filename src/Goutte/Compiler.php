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
      $content = preg_replace("#require_once 'Zend/.*?';#", '', php_strip_whitespace($file));

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
    return "<?php ".$this->getLicense()." require_once __DIR__.'/autoload.php'; __HALT_COMPILER();";
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
      'vendor/zend/library/Zend/Registry.php',
      //'vendor/zend/library/Zend/Date.php',
      'vendor/zend/library/Zend/Uri/Uri.php',
      'vendor/zend/library/Zend/Validator/Validator.php',
      'vendor/zend/library/Zend/Validator/AbstractValidator.php',
      'vendor/zend/library/Zend/Validator/Hostname.php',
      'vendor/zend/library/Zend/Validator/Ip.php',
      //'vendor/zend/library/Zend/Validator/Hostname/Biz.php',
      //'vendor/zend/library/Zend/Validator/Hostname/Cn.php',
      'vendor/zend/library/Zend/Validator/Hostname/Com.php',
      'vendor/zend/library/Zend/Validator/Hostname/Jp.php',
      'vendor/zend/library/Zend/Stdlib/Dispatchable.php',
      'vendor/zend/library/Zend/Stdlib/Message.php',
      'vendor/zend/library/Zend/Stdlib/MessageDescription.php',
      'vendor/zend/library/Zend/Stdlib/RequestDescription.php',
      'vendor/zend/library/Zend/Stdlib/Parameters.php',
      'vendor/zend/library/Zend/Stdlib/ParametersDescription.php',
      'vendor/zend/library/Zend/Stdlib/ResponseDescription.php',
      'vendor/zend/library/Zend/Loader/PluginClassLoader.php',
      'vendor/zend/library/Zend/Loader/PluginClassLocator.php',
      'vendor/zend/library/Zend/Loader/ShortNameLocator.php',
    );

    $dirs = array(
      'src/Goutte',
      'vendor/Symfony/Component/BrowserKit',
      'vendor/Symfony/Component/DomCrawler',
      'vendor/Symfony/Component/CssSelector',
      'vendor/Symfony/Component/Process',
      //'vendor/zend/library/Zend/Date',
      'vendor/zend/library/Zend/Uri',
      'vendor/zend/library/Zend/Http',
    );

    $finder = new Finder();
    $iterator = $finder->files()->name('*.php')->in($dirs);

    return array_merge($files, iterator_to_array($iterator));
  }
}
