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
use GuzzleHttp\Stream\Stream;
use Symfony\Component\BrowserKit\Cookie;

use GuzzleHttp\Message\Response as GuzzleResponse;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Subscriber\History;
use GuzzleHttp\Subscriber\Mock;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Post\PostFile;

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
        $this->historyPlugin = new History();
        $this->mockPlugin = new Mock();
        $this->mockPlugin->addResponse(new GuzzleResponse(200, array(), Stream::factory('<html><body><p>Hi</p></body></html>')));
        $guzzle = new GuzzleClient(array('redirect.disable' => true, 'base_url' => ''));
        $guzzle->getEmitter()->attach($this->mockPlugin);
        $guzzle->getEmitter()->attach($this->historyPlugin);

        return $guzzle;
    }

    public function testCreatesDefaultClient()
    {
        $client = new Client();
        $this->assertInstanceOf('GuzzleHttp\\ClientInterface', $client->getClient());
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
        $this->assertEquals('me', $request->getConfig()->get('auth')[0]);
        $this->assertEquals('**', $request->getConfig()->get('auth')[1]);
        $this->assertEquals('basic', $request->getConfig()->get('auth')[2]);
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
        $this->assertNull($request->getConfig()->get('auth')[0]);
        $this->assertNull($request->getConfig()->get('auth')[1]);
    }

    public function testUsesCookies()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $client->getCookieJar()->set(new Cookie('test', '123'));
        $crawler = $client->request('GET', 'http://www.example.com/');
        $request = $this->historyPlugin->getLastRequest();
        $this->assertEquals('test=123', $request->getHeader('Cookie'));
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

        $files = $request->getBody()->getFiles();
        $this->assertFile(reset($files), 'test', __FILE__, array(
          'Content-Type' => 'text/x-php',
          'Content-Disposition' => 'form-data; filename="ClientTest.php"; name="test"',
        ));
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
        $files = $request->getBody()->getFiles();
        $this->assertFile(reset($files), 'test', __FILE__, array(
          'Content-Type' => 'text/x-php',
          'Content-Disposition' => 'form-data; filename="ClientTest.php"; name="test"',
        ));
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
        $files = $request->getBody()->getFiles();
        $this->assertFile(reset($files), 'form[test]', __FILE__, array(
            'Content-Type' => 'text/x-php',
            'Content-Disposition' => 'form-data; filename="ClientTest.php"; name="form[test]"',
        ));
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
        $files = $request->getBody()->getFiles();
        $this->assertFile(reset($files), 'test', __FILE__, array(
          'Content-Type' => 'text/x-php',
          'Content-Disposition' => 'form-data; filename="ClientTest.php"; name="test"',
        ));
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

        $this->assertEquals(array(), $request->getBody()->getFiles());
    }

    public function testUsesCurlOptions()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $crawler = $client->request('GET', 'http://www.example.com/');
        $request = $this->historyPlugin->getLastRequest();
          $this->assertEquals(0, $request->getConfig()->get('curl')['CURLOPT_MAXREDIRS']);
        $this->assertEquals(30, $request->getConfig()->get('curl')['CURLOPT_TIMEOUT']);
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
        $this->mockPlugin->addResponse(new GuzzleResponse(200, [], Stream::factory('<html><body><p>Test</p></body></html>')));

        $client = new Client();
        $client->setClient($guzzle);

        $crawler = $client->request('GET', 'http://www.example.com/');
        $this->assertEquals('Test', $crawler->filter('p')->text());

        // Ensure that two requests were sent
        $this->assertEquals(2, count($this->historyPlugin));
    }

    public function testConvertsGuzzleHeadersToArrays()
    {
        $guzzle = $this->getGuzzle();

        $this->mockPlugin->clearQueue();
        $this->mockPlugin->addResponse(new GuzzleResponse(200, array(
            'Date' => 'Tue, 04 Jun 2013 13:22:41 GMT',
        )));

        $client = new Client();
        $client->setClient($guzzle);
        $client->request('GET', 'http://www.example.com/');
        $response = $client->getResponse();
        $headers = $response->getHeaders();

        $this->assertInternalType("array", array_shift($headers), "Header not converted from Guzzle\Http\Message\Header to array");
    }

    protected function assertFile(PostFile $postFile, $fieldName, $fileName, $headers)
    {
        $this->assertEquals($postFile->getName(), $fieldName);
        $this->assertEquals($postFile->getFilename(), $fileName);
        $this->assertEquals($postFile->getHeaders(), $headers);
    }
}
