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
use Guzzle\Http\Message\Header as GuzzleHeader;
use Guzzle\Http\Client as GuzzleClient;
use Guzzle\Plugin\Mock\MockPlugin;
use Guzzle\Plugin\History\HistoryPlugin;
use Guzzle\Http\Message\Response;
use Guzzle\Http\Message\PostFile;

/**
 * Goutte Client Test
 *
 * @author Michael Dowling <michael@guzzlephp.org>
 */
class ClientTest extends \PHPUnit_Framework_TestCase
{
    protected $historyPlugin;
    protected $mockPlugin;

    protected function getGuzzle()
    {
        $this->historyPlugin = new HistoryPlugin();
        $this->mockPlugin = new MockPlugin();
        $this->mockPlugin->addResponse(new GuzzleResponse(200, null, '<html><body><p>Hi</p></body></html>'));
        $guzzle = new GuzzleClient('', array('redirect.disable' => true));
        $guzzle->getEventDispatcher()->addSubscriber($this->mockPlugin);
        $guzzle->getEventDispatcher()->addSubscriber($this->historyPlugin);

        return $guzzle;
    }

    public function testCreatesDefaultClient()
    {
        $client = new Client();
        $this->assertInstanceOf('Guzzle\\Http\\ClientInterface', $client->getClient());
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
        $crawler = $client->request('GET', 'http://www.example.com/');
        $this->assertEquals('test', $this->historyPlugin->getLastRequest()->getHeader('X-Test'));
    }

    public function testCustomUserAgent()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $client->setHeader('User-Agent', 'foo');
        $crawler = $client->request('GET', 'http://www.example.com/');
        $this->assertEquals('foo', $this->historyPlugin->getLastRequest()->getHeader('User-Agent'));
    }

    public function testUsesAuth()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $client->setAuth('me', '**');
        $crawler = $client->request('GET', 'http://www.example.com/');
        $request = $this->historyPlugin->getLastRequest();
        $this->assertEquals('me', $request->getUsername());
        $this->assertEquals('**', $request->getPassword());
    }

    public function testResetsAuth()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $client->setAuth('me', '**');
        $client->resetAuth();
        $crawler = $client->request('GET', 'http://www.example.com/');
        $request = $this->historyPlugin->getLastRequest();
        $this->assertNull($request->getUsername());
        $this->assertNull($request->getPassword());
    }

    public function testUsesCookies()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $client->getCookieJar()->set(new Cookie('test', '123'));
        $crawler = $client->request('GET', 'http://www.example.com/');
        $request = $this->historyPlugin->getLastRequest();
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

        $crawler = $client->request('POST', 'http://www.example.com/', array(), $files);
        $request = $this->historyPlugin->getLastRequest();

        $this->assertEquals(array(
            'test' => array(
                new PostFile('test', __FILE__, 'text/x-php')
            )
        ), $request->getPostFiles());
    }

    public function testUsesPostNamedFiles()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $files = array(
            'test' => __FILE__
        );

        $crawler = $client->request('POST', 'http://www.example.com/', array(), $files);
        $request = $this->historyPlugin->getLastRequest();

        $this->assertEquals(array(
            'test' => array(
                new PostFile('test', __FILE__, 'text/x-php')
            )
        ), $request->getPostFiles());
    }

    public function testUsesPostFilesNestedFields()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $files = array(
            'form' => array(
                'test' => array(
                    'name' => 'test.txt',
                    'tmp_name' => __FILE__
                ),
            ),
        );

        $crawler = $client->request('POST', 'http://www.example.com/', array(), $files);
        $request = $this->historyPlugin->getLastRequest();

        $this->assertEquals(array(
            'form[test]' => array(
                new PostFile('form[test]', __FILE__, 'text/x-php')
            )
        ), $request->getPostFiles());
    }

    public function testUsesPostFilesOnClientSide()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $files = array(
            'test' => __FILE__,
        );

        $crawler = $client->request('POST', 'http://www.example.com/', array(), $files);
        $request = $this->historyPlugin->getLastRequest();

        $this->assertEquals(array(
            'test' => array(
                new PostFile('test', __FILE__, 'text/x-php')
            )
        ), $request->getPostFiles());
    }

    public function testUsesPostFilesUploadError()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $files = array(
            'test' => array(
                'name' => '',
                'type' => '',
                'tmp_name' => '',
                'error' => 4,
                'size' => 0,
            ),
        );

        $crawler = $client->request('POST', 'http://www.example.com/', array(), $files);
        $request = $this->historyPlugin->getLastRequest();

        $this->assertEquals(array(), $request->getPostFiles());
    }

    public function testUsesCurlOptions()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $crawler = $client->request('GET', 'http://www.example.com/');
        $request = $this->historyPlugin->getLastRequest();
        $this->assertEquals(0, $request->getCurlOptions()->get(CURLOPT_MAXREDIRS));
        $this->assertEquals(30, $request->getCurlOptions()->get(CURLOPT_TIMEOUT));
    }

    public function testCreatesResponse()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $crawler = $client->request('GET', 'http://www.example.com/');
        $this->assertEquals('Hi', $crawler->filter('p')->text());
    }

    public function testHandlesRedirectsCorrectly()
    {
        $guzzle = $this->getGuzzle();

        $this->mockPlugin->clearQueue();
        $this->mockPlugin->addResponse(new GuzzleResponse(301, array(
            'Location' => 'http://www.example.com/'
        )));
        $this->mockPlugin->addResponse(new GuzzleResponse(200, null, '<html><body><p>Test</p></body></html>'));

        $client = new Client();
        $client->setClient($guzzle);

        $crawler = $client->request('GET', 'http://www.example.com/');
        $this->assertEquals('Test', $crawler->filter('p')->text());

        // Ensure that two requests were sent
        $this->assertEquals(2, count($this->historyPlugin));
    }

    public function testConvertsGuzzleHeadersToArrays()
    {
        if (!class_exists("Guzzle\Http\Message\Header")) {
            $this->markTestSkipped("Guzzle ~3.6 required");
        }

        $guzzle = $this->getGuzzle();

        $this->mockPlugin->clearQueue();
        $this->mockPlugin->addResponse(new GuzzleResponse(200, array(
            new GuzzleHeader('Date', 'Tue, 04 Jun 2013 13:22:41 GMT'),
        )));

        $client = new Client();
        $client->setClient($guzzle);
        $client->request('GET', 'http://www.example.com/');
        $response = $client->getResponse();
        $headers = $response->getHeaders();

        $this->assertInternalType("array", array_shift($headers), "Header not converted from Guzzle\Http\Message\Header to array");
    }
}
