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
use Http\Adapter\Guzzle6\Client as GuzzleAdapter;
use Http\Client\Exception\RequestException as HttpRequestException;
use Http\Client\HttpClient;
use Http\Message\Authentication;
use Http\Message\Authentication\BasicAuth;
use Psr\Http\Message\RequestInterface;
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

    protected function getGuzzle(array $responses = array())
    {
        if (empty($responses)) {
            $responses = array(new GuzzleResponse(200, array(), '<html><body><p>Hi</p></body></html>'));
        }
        $this->mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($this->mock);
        $this->history = array();
        $handlerStack->push(Middleware::history($this->history));
        $guzzle = new GuzzleClient(array('redirect.disable' => true, 'base_uri' => '', 'handler' => $handlerStack));

        return new GuzzleAdapter($guzzle);
    }

    public function testCreatesDefaultAdapter()
    {
        $client = new Client();
        $this->assertInstanceOf(HttpClient::class, $client->getAdapter());
    }

    public function testUsesCustomClient()
    {
        $guzzle = new GuzzleAdapter();
        $client = new Client();
        $this->assertSame($client, $client->setAdapter($guzzle));
        $this->assertSame($guzzle, $client->getAdapter());
    }

    public function testUsesCustomHeaders()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setAdapter($guzzle);
        $client->setHeader('X-Test', 'test');
        $client->request('GET', 'http://www.example.com/');
        $this->assertEquals('test', end($this->history)['request']->getHeaderLine('X-Test'));
    }

    public function testCustomUserAgent()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setAdapter($guzzle);
        $client->setHeader('User-Agent', 'foo');
        $client->request('GET', 'http://www.example.com/');
        $this->assertEquals('foo', end($this->history)['request']->getHeaderLine('User-Agent'));
    }

    public function testUsesAuth()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setAdapter($guzzle);
        $client->setAuth(new BasicAuth('me', '**'));
        $client->request('GET', 'http://www.example.com/');
        $request = end($this->history)['request'];
        $this->assertEquals('Basic bWU6Kio=', $request->getHeaderLine('Authorization'));
    }

    public function testResetsAuth()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setAdapter($guzzle);
        $client->setAuth(new BasicAuth('me', '**'));
        $client->resetAuth();
        $client->request('GET', 'http://www.example.com/');
        $request = end($this->history)['request'];
        $this->assertEquals('', $request->getHeaderLine('authorization'));
    }

    public function testUsesCookies()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setAdapter($guzzle);
        $client->getCookieJar()->set(new Cookie('test', '123'));
        $client->request('GET', 'http://www.example.com/');
        $request = end($this->history)['request'];
        $this->assertEquals('test=123', $request->getHeaderLine('Cookie'));
    }

    public function testUsesCookiesWithCustomPort()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setAdapter($guzzle);
        $client->getCookieJar()->set(new Cookie('test', '123'));
        $client->request('GET', 'http://www.example.com:8000/');
        $request = end($this->history)['request'];
        $this->assertEquals('test=123', $request->getHeaderLine('Cookie'));
    }

    public function testUsesPostFiles()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setAdapter($guzzle);
        $files = array(
            'test' => array(
                'name' => 'test.txt',
                'tmp_name' => __DIR__.'/fixtures.txt',
            ),
        );

        $client->request('POST', 'http://www.example.com/', array(), $files);
        $request = end($this->history)['request'];

        $stream = $request->getBody();
        $boundary = $this->getBoundary($request);

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
        $client->setAdapter($guzzle);
        $files = array(
            'test' => __DIR__.'/fixtures.txt',
        );

        $client->request('POST', 'http://www.example.com/', array(), $files);
        $request = end($this->history)['request'];

        $stream = $request->getBody();
        $boundary = $this->getBoundary($request);
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
        $client->setAdapter($guzzle);
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
        $boundary = $this->getBoundary($request);
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
        $client->setAdapter($guzzle);
        $files = array(
            'test' => __DIR__.'/fixtures.txt',
        );
        $params = array(
            'foo' => 'bar',
        );

        $client->request('POST', 'http://www.example.com/', $params, $files);
        $request = end($this->history)['request'];

        $stream = $request->getBody();
        $boundary = $this->getBoundary($request);
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
        $client->setAdapter($guzzle);
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
        $boundary = $this->getBoundary($request);
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
        $client->setAdapter($guzzle);
        $files = array(
            'test' => __DIR__.'/fixtures.txt',
        );

        $client->request('POST', 'http://www.example.com/', array(), $files);
        $request = end($this->history)['request'];

        $stream = $request->getBody();
        $boundary = $this->getBoundary($request);
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
        $client->setAdapter($guzzle);
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
        $boundary = $this->getBoundary($request);

        $this->assertEquals("--$boundary--\r\n", $stream->getContents());
    }

    public function testCreatesResponse()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client();
        $client->setAdapter($guzzle);
        $crawler = $client->request('GET', 'http://www.example.com/');
        $this->assertEquals('Hi', $crawler->filter('p')->text());
    }

    public function testHandlesRedirectsCorrectly()
    {
        $guzzle = $this->getGuzzle(array(
            new GuzzleResponse(301, array(
                'Location' => 'http://www.example.com/',
            )),
            new GuzzleResponse(200, array(), '<html><body><p>Test</p></body></html>'),
        ));

        $client = new Client();
        $client->setAdapter($guzzle);

        $crawler = $client->request('GET', 'http://www.example.com/');
        $this->assertEquals('Test', $crawler->filter('p')->text());

        // Ensure that two requests were sent
        $this->assertEquals(2, count($this->history));
    }

    public function testConvertsGuzzleHeadersToArrays()
    {
        $guzzle = $this->getGuzzle(array(
            new GuzzleResponse(200, array(
                'Date' => 'Tue, 04 Jun 2013 13:22:41 GMT',
            )),
        ));

        $client = new Client();
        $client->setAdapter($guzzle);
        $client->request('GET', 'http://www.example.com/');
        $response = $client->getResponse();
        $headers = $response->getHeaders();

        $this->assertInternalType('array', array_shift($headers), 'Header not converted from Guzzle\Http\Message\Header to array');
    }

    public function testNullResponseException()
    {
        $this->setExpectedException(HttpRequestException::class);
        $guzzle = $this->getGuzzle(array(
            new RequestException('', $this->getMock(RequestInterface::class)),
        ));
        $client = new Client();
        $client->setAdapter($guzzle);
        $client->request('GET', 'http://www.example.com/');
        $client->getResponse();
    }

    public function testHttps()
    {
        $guzzle = $this->getGuzzle(array(
            new GuzzleResponse(200, array(), '<html><body><p>Test</p></body></html>'),
        ));

        $client = new Client();
        $client->setAdapter($guzzle);
        $crawler = $client->request('GET', 'https://www.example.com/');
        $this->assertEquals('Test', $crawler->filter('p')->text());
    }

    public function testCustomUserAgentConstructor()
    {
        $guzzle = $this->getGuzzle();
        $client = new Client(array(
          'HTTP_HOST' => '1.2.3.4',
          'HTTP_USER_AGENT' => 'SomeHost',
        ));
        $client->setAdapter($guzzle);
        $client->request('GET', 'http://www.example.com/');
        $this->assertEquals('SomeHost', end($this->history)['request']->getHeaderLine('User-Agent'));
    }

    public function testResetHeaders()
    {
        $client = new Client();
        $client->setHeader('X-Test', 'test');

        $reflectionProperty = new \ReflectionProperty('Goutte\Client', 'headers');
        $reflectionProperty->setAccessible(true);
        $this->assertEquals(array('x-test' => 'test'), $reflectionProperty->getValue($client));

        $client->resetHeaders();
        $this->assertEquals(array(), $reflectionProperty->getValue($client));
    }

    public function testRestart()
    {
        $client = new Client();
        $client->setHeader('X-Test', 'test');
        $client->setAuth(new BasicAuth('foo', 'bar'));

        $headersReflectionProperty = new \ReflectionProperty('Goutte\Client', 'headers');
        $headersReflectionProperty->setAccessible(true);
        $this->assertEquals(array('x-test' => 'test'), $headersReflectionProperty->getValue($client));

        $authReflectionProperty = new \ReflectionProperty('Goutte\Client', 'auth');
        $authReflectionProperty->setAccessible(true);
        $this->assertInstanceOf(Authentication::class, $authReflectionProperty->getValue($client));

        $client->restart();
        $this->assertEquals(array(), $headersReflectionProperty->getValue($client));
        $this->assertNull($authReflectionProperty->getValue($client));
    }

    private function getBoundary($request)
    {
        if (!preg_match('#boundary="(.+)"$#', $request->getHeader('content-type')[0], $matches)) {
            throw new \Exception('Could not match any boundary.');
        }

        return $matches[1];
    }
}
