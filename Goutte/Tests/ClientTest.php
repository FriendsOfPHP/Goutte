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
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use GuzzleHttp\Middleware;
use Symfony\Component\BrowserKit\Cookie;

/**
 * Goutte Client Test.
 *
 * @author Michael Dowling <michael@guzzlephp.org>
 * @author Charles Sarrazin <charles@sarraz.in>
 */
class ClientTest extends \PHPUnit_Framework_TestCase
{
    protected $history;
    /** @var MockHandler */
    protected $mock;

    protected function getGuzzle(array $responses = [])
    {
        if (empty($responses)) {
            $responses = [new GuzzleResponse(200, [], '<html><body><p>Hi</p></body></html>')];
        }
        $this->mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($this->mock);
        $this->history = [];
        $handlerStack->push(Middleware::history($this->history));
        $guzzle = new GuzzleClient(array('redirect.disable' => true, 'base_uri' => '', 'handler' => $handlerStack));

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
        $client->request('GET', 'http://www.example.com/');
        $this->assertEquals('test', end($this->history)['request']->getHeaderLine('X-Test'));
    }

    public function testCustomUserAgent()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $client->setHeader('User-Agent', 'foo');
        $client->request('GET', 'http://www.example.com/');
        $this->assertEquals('Symfony2 BrowserKit, foo', end($this->history)['request']->getHeaderLine('User-Agent'));
    }

    public function testUsesAuth()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $client->setAuth('me', '**');
        $client->request('GET', 'http://www.example.com/');
        $request = end($this->history)['request'];
        $this->assertEquals('Basic bWU6Kio=', $request->getHeaderLine('Authorization'));
    }

    public function testResetsAuth()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $client->setAuth('me', '**');
        $client->resetAuth();
        $client->request('GET', 'http://www.example.com/');
        $request = end($this->history)['request'];
        $this->assertEquals('', $request->getHeaderLine('authorization'));
    }

    public function testUsesCookies()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $client->getCookieJar()->set(new Cookie('test', '123'));
        $client->request('GET', 'http://www.example.com/');
        $request = end($this->history)['request'];
        $this->assertEquals('test=123', $request->getHeaderLine('Cookie'));
    }

    public function testUsesCookiesWithCustomPort()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $client->getCookieJar()->set(new Cookie('test', '123'));
        $client->request('GET', 'http://www.example.com:8000/');
        $request = end($this->history)['request'];
        $this->assertEquals('test=123', $request->getHeaderLine('Cookie'));
    }

    public function testUsesPostFiles()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $files = array(
            'test' => array(
                'name' => 'test.txt',
                'tmp_name' => __DIR__.'/fixtures.txt',
            ),
        );

        $client->request('POST', 'http://www.example.com/', array(), $files);
        $request = end($this->history)['request'];

        $stream = $request->getBody();
        $boundary = $stream->getBoundary();
        $this->assertEquals(
            "--$boundary\r\nContent-Disposition: form-data; name=\"test\"; filename=\"test.txt\"\r\nContent-Length: 4\r\n"
            ."Content-Type: text/plain\r\n\r\nfoo\n\r\n--$boundary--\r\n",
            $stream->getContents()
        );
    }

    public function testUsesPostNamedFiles()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $files = array(
            'test' => __DIR__.'/fixtures.txt',
        );

        $client->request('POST', 'http://www.example.com/', array(), $files);
        $request = end($this->history)['request'];

        $stream = $request->getBody();
        $boundary = $stream->getBoundary();
        $this->assertEquals(
            "--$boundary\r\nContent-Disposition: form-data; name=\"test\"; filename=\"fixtures.txt\"\r\nContent-Length: 4\r\n"
            ."Content-Type: text/plain\r\n\r\nfoo\n\r\n--$boundary--\r\n",
            $stream->getContents()
        );
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
                    'tmp_name' => __DIR__.'/fixtures.txt',
                ),
            ),
        );

        $client->request('POST', 'http://www.example.com/', array(), $files);
        $request = end($this->history)['request'];

        $stream = $request->getBody();
        $boundary = $stream->getBoundary();
        $this->assertEquals(
            "--$boundary\r\nContent-Disposition: form-data; name=\"form[test]\"; filename=\"test.txt\"\r\nContent-Length: 4\r\n"
            ."Content-Type: text/plain\r\n\r\nfoo\n\r\n--$boundary--\r\n",
            $stream->getContents()
        );
    }

    public function testPostFormWithFiles()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $files = array(
            'test' => __DIR__.'/fixtures.txt',
        );
        $params = array(
            'foo' => 'bar',
        );

        $client->request('POST', 'http://www.example.com/', $params, $files);
        $request = end($this->history)['request'];

        $stream = $request->getBody();
        $boundary = $stream->getBoundary();
        $this->assertEquals(
            "--$boundary\r\nContent-Disposition: form-data; name=\"foo\"\r\nContent-Length: 3\r\n"
            ."\r\nbar\r\n"
            ."--$boundary\r\nContent-Disposition: form-data; name=\"test\"; filename=\"fixtures.txt\"\r\nContent-Length: 4\r\n"
            ."Content-Type: text/plain\r\n\r\nfoo\n\r\n--$boundary--\r\n",
        $stream->getContents());
    }

    public function testPostEmbeddedFormWithFiles()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $files = array(
            'test' => __DIR__.'/fixtures.txt',
        );
        $params = array(
            'foo' => array(
                'bar' => 'baz',
            ),
        );

        $client->request('POST', 'http://www.example.com/', $params, $files);
        $request = end($this->history)['request'];

        $stream = $request->getBody();
        $boundary = $stream->getBoundary();
        $this->assertEquals(
            "--$boundary\r\nContent-Disposition: form-data; name=\"foo[bar]\"\r\nContent-Length: 3\r\n"
            ."\r\nbaz\r\n"
            ."--$boundary\r\nContent-Disposition: form-data; name=\"test\"; filename=\"fixtures.txt\"\r\nContent-Length: 4\r\n"
            ."Content-Type: text/plain\r\n\r\nfoo\n\r\n--$boundary--\r\n",
        $stream->getContents());
    }

    public function testUsesPostFilesOnClientSide()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setClient($guzzle);
        $files = array(
            'test' => __DIR__.'/fixtures.txt',
        );

        $client->request('POST', 'http://www.example.com/', array(), $files);
        $request = end($this->history)['request'];

        $stream = $request->getBody();
        $boundary = $stream->getBoundary();
        $this->assertEquals(
            "--$boundary\r\nContent-Disposition: form-data; name=\"test\"; filename=\"fixtures.txt\"\r\nContent-Length: 4\r\n"
            ."Content-Type: text/plain\r\n\r\nfoo\n\r\n--$boundary--\r\n",
            $stream->getContents()
        );
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

        $client->request('POST', 'http://www.example.com/', array(), $files);
        $request = end($this->history)['request'];
        $stream = $request->getBody();
        $boundary = $stream->getBoundary();

        $this->assertEquals("--$boundary--\r\n", $stream->getContents());
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
        $guzzle = $this->getGuzzle([
            new GuzzleResponse(301, array(
                'Location' => 'http://www.example.com/',
            )),
            new GuzzleResponse(200, [], '<html><body><p>Test</p></body></html>'),
        ]);

        $client = new Client();
        $client->setClient($guzzle);

        $crawler = $client->request('GET', 'http://www.example.com/');
        $this->assertEquals('Test', $crawler->filter('p')->text());

        // Ensure that two requests were sent
        $this->assertEquals(2, count($this->history));
    }

    public function testConvertsGuzzleHeadersToArrays()
    {
        $guzzle = $this->getGuzzle([
            new GuzzleResponse(200, array(
                'Date' => 'Tue, 04 Jun 2013 13:22:41 GMT',
            )),
        ]);

        $client = new Client();
        $client->setClient($guzzle);
        $client->request('GET', 'http://www.example.com/');
        $response = $client->getResponse();
        $headers = $response->getHeaders();

        $this->assertInternalType('array', array_shift($headers), 'Header not converted from Guzzle\Http\Message\Header to array');
    }

    public function testNullResponseException()
    {
        $this->setExpectedException('GuzzleHttp\Exception\RequestException');
        $guzzle = $this->getGuzzle([
            new RequestException('', $this->getMock('Psr\Http\Message\RequestInterface')),
        ]);
        $client = new Client();
        $client->setClient($guzzle);
        $client->request('GET', 'http://www.example.com/');
        $client->getResponse();
    }

    public function testHttps()
    {
        $guzzle = $this->getGuzzle([
            new GuzzleResponse(200, [], '<html><body><p>Test</p></body></html>'),
        ]);

        $client = new Client();
        $client->setClient($guzzle);
        $crawler = $client->request('GET', 'https://www.example.com/');
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
        $client->request('GET', 'http://www.example.com/');
        $this->assertEquals('SomeHost', end($this->history)['request']->getHeaderLine('User-Agent'));
    }
}
