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
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Message\Response as GuzzleResponse;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Subscriber\History;
use GuzzleHttp\Subscriber\Mock;
use GuzzleHttp\Post\PostFile;
use Symfony\Component\BrowserKit\Cookie;

/**
 * Goutte Client Test
 *
 * @author Michael Dowling <michael@guzzlephp.org>
 */
class ClientTest extends \PHPUnit_Framework_TestCase
{
    protected $history;
    protected $mock;

    protected function getGuzzle()
    {
        $this->history = new History();
        $this->mock = new Mock();
        $this->mock->addResponse(new GuzzleResponse(200, array(), Stream::factory('<html><body><p>Hi</p></body></html>')));
        $guzzle = new GuzzleClient(array('redirect.disable' => true, 'base_url' => ''));
        $guzzle->getEmitter()->attach($this->mock);
        $guzzle->getEmitter()->attach($this->history);

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
        $this->assertEquals('test', $this->history->getLastRequest()->getHeader('X-Test'));
    }

    public function testCustomUserAgent()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $client->setHeader('User-Agent', 'foo');
        $crawler = $client->request('GET', 'http://www.example.com/');
        $this->assertEquals('foo', $this->history->getLastRequest()->getHeader('User-Agent'));
    }

    public function testUsesAuth()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $client->setAuth('me', '**');
        $crawler = $client->request('GET', 'http://www.example.com/');
        $request = $this->history->getLastRequest();
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
        $request = $this->history->getLastRequest();
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
        $request = $this->history->getLastRequest();
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
                'tmp_name' => __FILE__,
            ),
        );

        $crawler = $client->request('POST', 'http://www.example.com/', array(), $files);
        $request = $this->history->getLastRequest();

        $files = $request->getBody()->getFiles();
        $this->assertFile(reset($files), 'test', 'test.txt', array(
          'Content-Type' => 'text/plain',
          'Content-Disposition' => 'form-data; filename="test.txt"; name="test"',
        ));
    }

    public function testUsesPostNamedFiles()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $files = array(
            'test' => __FILE__,
        );

        $crawler = $client->request('POST', 'http://www.example.com/', array(), $files);
        $request = $this->history->getLastRequest();
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
                    'tmp_name' => __FILE__,
                ),
            ),
        );

        $crawler = $client->request('POST', 'http://www.example.com/', array(), $files);
        $request = $this->history->getLastRequest();
        $files = $request->getBody()->getFiles();
        $this->assertFile(reset($files), 'form[test]', 'test.txt', array(
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'form-data; filename="test.txt"; name="form[test]"',
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
        $request = $this->history->getLastRequest();
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
        $request = $this->history->getLastRequest();

        $this->assertEquals(array(), $request->getBody()->getFiles());
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

        $this->mock->clearQueue();
        $this->mock->addResponse(new GuzzleResponse(301, array(
            'Location' => 'http://www.example.com/',
        )));
        $this->mock->addResponse(new GuzzleResponse(200, [], Stream::factory('<html><body><p>Test</p></body></html>')));

        $client = new Client();
        $client->setClient($guzzle);

        $crawler = $client->request('GET', 'http://www.example.com/');
        $this->assertEquals('Test', $crawler->filter('p')->text());

        // Ensure that two requests were sent
        $this->assertEquals(2, count($this->history));
    }

    public function testConvertsGuzzleHeadersToArrays()
    {
        $guzzle = $this->getGuzzle();

        $this->mock->clearQueue();
        $this->mock->addResponse(new GuzzleResponse(200, array(
            'Date' => 'Tue, 04 Jun 2013 13:22:41 GMT',
        )));

        $client = new Client();
        $client->setClient($guzzle);
        $client->request('GET', 'http://www.example.com/');
        $response = $client->getResponse();
        $headers = $response->getHeaders();

        $this->assertInternalType("array", array_shift($headers), "Header not converted from Guzzle\Http\Message\Header to array");
    }

    public function testNullResponseException()
    {
        $this->setExpectedException('GuzzleHttp\Exception\RequestException');
        $guzzle = $this->getGuzzle();
        $this->mock->clearQueue();
        $exception = new RequestException('', $this->getMock('GuzzleHttp\Message\RequestInterface'));
        $this->mock->addException($exception);
        $client = new Client();
        $client->setClient($guzzle);
        $client->request('GET', 'http://www.example.com/');
        $response = $client->getResponse();
    }

    protected function assertFile(PostFile $postFile, $fieldName, $fileName, $headers)
    {
        $this->assertEquals($postFile->getName(), $fieldName);
        $this->assertEquals($postFile->getFilename(), $fileName);

        $postFileHeaders = $postFile->getHeaders();

        // Note: Sort 'Content-Disposition' values before comparing, because the order changed in Guzzle 4.2.2
        $postFileHeaders['Content-Disposition'] = explode('; ', $postFileHeaders['Content-Disposition']);
        sort($postFileHeaders['Content-Disposition']);
        $headers['Content-Disposition'] = explode('; ', $headers['Content-Disposition']);
        sort($headers['Content-Disposition']);

        $this->assertEquals($postFileHeaders, $headers);
    }

    public function testHttps()
    {
        $guzzle = $this->getGuzzle();

        $this->mock->clearQueue();
        $this->mock->addResponse(new GuzzleResponse(200, [], Stream::factory('<html><body><p>Test</p></body></html>')));
        $client = new Client();
        $client->setClient($guzzle);
        $crawler = $client->request('GET', 'https://www.example.com/');
        $this->assertEquals('https', $this->history->getLastRequest()->getScheme());
        $this->assertEquals('Test', $crawler->filter('p')->text());
    }

    public function testCustomUserAgentConstructor()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client([
          'HTTP_HOST' => '1.2.3.4',
          'HTTP_USER_AGENT' => 'SomeHost',
        ]);
        $client->setClient($guzzle);
        $crawler = $client->request('GET', 'http://www.example.com/');
        $this->assertEquals('SomeHost', $this->history->getLastRequest()->getHeader('User-Agent'));
    }
}
