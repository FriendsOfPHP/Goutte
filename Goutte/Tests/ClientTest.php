<?php

/*
 * This file is part of the Goutte package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Goutte\Tests;

use Goutte\Client;
use PHPUnit\Framework\TestCase;
use Symfony\Component\BrowserKit\HttpBrowser;

class ClientTest extends TestCase
{
    public function testNew()
    {
        $client = new Client();
        $this->assertInstanceOf(HttpBrowser::class, $client);
    }
}
