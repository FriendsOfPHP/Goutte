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
use Symfony\Component\BrowserKit\Cookie;

use Guzzle\Http\Message\Response as GuzzleResponse;
use Guzzle\Service\Client as GuzzleClient;
use Guzzle\Service\Plugin\MockPlugin;
use Guzzle\Http\Plugin\HistoryPlugin;
use Guzzle\Http\Message\Response;

/**
 * Goutte Client Test
 *
 * @author Michael Dowling <michael@guzzlephp.org>
 */
class ClientTest extends \PHPUnit_Framework_TestCase
{
    protected $history;

    protected function getGuzzle()
    {
        $this->history = new HistoryPlugin();
        $mock = new MockPlugin();
        $mock->addResponse(new GuzzleResponse(200, null, '<html><body><p>Hi</p></body></html>'));
        $guzzle = new GuzzleClient();
        $guzzle->getEventManager()->attach($mock);
        $guzzle->getEventManager()->attach($this->history);

        return $guzzle;
    }

    public function testCreatesDefaultClient()
    {
        $client = new Client();
        $this->assertInstanceOf('Guzzle\\Service\\Client', $client->getClient());
    }

    public function testUsesCustomClient()
    {
        $guzzle = new GuzzleClient();
        $client = new Client();
        $this->assertSame($client, $client->setClient($guzzle));
        $this->assertSame($guzzle, $client->getClient());
    }

    public function testUsesCustomHeaders()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $client->setHeader('X-Test', 'test');
        $crawler = $client->request('GET', 'http://test.com/');
        $this->assertEquals('test', $this->history->getLastRequest()->getHeader('X-Test'));
    }

    public function testUsesAuth()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $client->setAuth('me', '**');
        $crawler = $client->request('GET', 'http://test.com/');
        $request = $this->history->getLastRequest();
        $this->assertEquals('me', $request->getUsername());
        $this->assertEquals('**', $request->getPassword());
    }

    public function testUsesCookies()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $client->getCookieJar()->set(new Cookie('test', '123'));
        $crawler = $client->request('GET', 'http://test.com/');
        $request = $this->history->getLastRequest();
        $this->assertEquals('123', $request->getCookie('test'));
    }

    public function testUsesPostFiles()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $files = array(
            'test' => array(
                'name' => 'test.txt',
                'tmp_name' => __FILE__
            )
        );

        $crawler = $client->request('POST', 'http://test.com/', array(), $files);
        $request = $this->history->getLastRequest();

        $this->assertEquals(array(
            'test' => __FILE__
        ), $request->getPostFiles());
    }

    public function testUsesCurlOptions()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $crawler = $client->request('GET', 'http://test.com/');
        $request = $this->history->getLastRequest();
        $this->assertEquals(0, $request->getCurlOptions()->get(CURLOPT_MAXREDIRS));
        $this->assertEquals(30, $request->getCurlOptions()->get(CURLOPT_TIMEOUT));
    }

    public function testCreatesResponse()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $crawler = $client->request('GET', 'http://test.com/');
        $this->assertEquals('Hi', $crawler->filter('p')->text());
    }

    public function testHandlesRedirectsCorrectly()
    {
        $guzzle = $this->getGuzzle();
        $plugins = $guzzle->getEventManager()->getAttached('Guzzle\\Service\\Plugin\\MockPlugin');
        $mock = $plugins[0];

        $mock->clearQueue();
        $mock->addResponse(new GuzzleResponse(301, array(
            'Location' => 'http://www.test.com/'
        )));
        $mock->addResponse(new GuzzleResponse(200, null, '<html><body><p>Test</p></body></html>'));

        $client = new Client();
        $client->setClient($guzzle);

        $crawler = $client->request('GET', 'http://test.com/');
        $this->assertEquals('Test', $crawler->filter('p')->text());

        // Ensure that two requests were sent
        $this->assertEquals(2, count($this->history));
    }
}
