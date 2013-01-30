<?php

/*
 * This file is part of the Goutte package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Goutte;

use Symfony\Component\BrowserKit\Client as BaseClient;
use Symfony\Component\BrowserKit\Response;

use Guzzle\Http\Exception\CurlException;
use Guzzle\Http\Exception\BadResponseException;
use Guzzle\Http\Message\Response as GuzzleResponse;
use Guzzle\Http\ClientInterface as GuzzleClientInterface;
use Guzzle\Http\Client as GuzzleClient;
use Guzzle\Http\Message\EntityEnclosingRequestInterface;

/**
 * Client.
 *
 * @package Goutte
 * @author  Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author  Michael Dowling <michael@guzzlephp.org>
 */
class Client extends BaseClient
{
    const VERSION = '0.2';

    protected $headers = array();
    protected $auth = null;
    protected $client;

    public function setClient(GuzzleClientInterface $client)
    {
        $this->client = $client;

        return $this;
    }

    public function getClient()
    {
        if (!$this->client) {
            $this->client = new GuzzleClient('', array('redirect.disable' => true));
        }

        return $this->client;
    }

    public function setHeader($name, $value)
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function setAuth($user, $password = '', $type = CURLAUTH_BASIC)
    {
        $this->auth = array(
            'user' => $user,
            'password' => $password,
            'type'     => $type
        );

        return $this;
    }
    
    /**
     * Calls a URI using GET method.
     *
     * @param string  $uri           The URI to fetch
     * @param array   $parameters    The Request parameters
     * @param array   $files         The files
     * @param array   $server        The server parameters (HTTP headers are referenced with a HTTP_ prefix as PHP does)
     * @param string  $content       The raw body data
     * @param Boolean $changeHistory Whether to update the history or not (only used internally for back(), forward(), and reload())
     *
     * @return \Symfony\Component\DomCrawler\Crawler
     */
    public function get($uri, array $parameters = array(), array $files = array(), array $server = array(), $content = null, $changeHistory = true)
    {
        return $this->request("GET", $uri, $parameters, $files, $server, $content, $changeHistory);
    }
    
    /**
     * Calls a URI using POST method.
     *
     * @param string  $uri           The URI to fetch
     * @param array   $parameters    The Request parameters
     * @param array   $files         The files
     * @param array   $server        The server parameters (HTTP headers are referenced with a HTTP_ prefix as PHP does)
     * @param string  $content       The raw body data
     * @param Boolean $changeHistory Whether to update the history or not (only used internally for back(), forward(), and reload())
     *
     * @return \Symfony\Component\DomCrawler\Crawler
     */
    public function post($uri, array $parameters = array(), array $files = array(), array $server = array(), $content = null, $changeHistory = true)
    {
        return $this->request("POST", $uri, $parameters, $files, $server, $content, $changeHistory);
    }
    
    /**
     * Calls a URI using PUT method.
     *
     * @param string  $uri           The URI to fetch
     * @param array   $parameters    The Request parameters
     * @param array   $files         The files
     * @param array   $server        The server parameters (HTTP headers are referenced with a HTTP_ prefix as PHP does)
     * @param string  $content       The raw body data
     * @param Boolean $changeHistory Whether to update the history or not (only used internally for back(), forward(), and reload())
     *
     * @return \Symfony\Component\DomCrawler\Crawler
     */
    public function put($uri, array $parameters = array(), array $files = array(), array $server = array(), $content = null, $changeHistory = true)
    {
        return $this->request("PUT", $uri, $parameters, $files, $server, $content, $changeHistory);
    }
    
    /**
     * Calls a URI using DELETE method.
     *
     * @param string  $uri           The URI to fetch
     * @param array   $parameters    The Request parameters
     * @param array   $files         The files
     * @param array   $server        The server parameters (HTTP headers are referenced with a HTTP_ prefix as PHP does)
     * @param string  $content       The raw body data
     * @param Boolean $changeHistory Whether to update the history or not (only used internally for back(), forward(), and reload())
     *
     * @return \Symfony\Component\DomCrawler\Crawler
     */
    public function delete($uri, array $parameters = array(), array $files = array(), array $server = array(), $content = null, $changeHistory = true)
    {
        return $this->request("DELETE", $uri, $parameters, $files, $server, $content, $changeHistory);
    }

    protected function doRequest($request)
    {
        $headers = array();
        foreach ($request->getServer() as $key => $val) {
            $key = ucfirst(strtolower(str_replace(array('_', 'HTTP-'), array('-', ''), $key)));
            if (!isset($headers[$key])) {
                $headers[$key] = $val;
            }
        }

        $body = null;
        if (!in_array($request->getMethod(), array('GET','HEAD'))) {
            if (null !== $request->getContent()) {
                $body = $request->getContent();
            } else {
                $body = $request->getParameters();
            }
        }

        $guzzleRequest = $this->getClient()->createRequest(
            $request->getMethod(),
            $request->getUri(),
            $headers,
            $body
        );

        foreach ($this->headers as $name => $value) {
            $guzzleRequest->setHeader($name, $value);
        }

        if ($this->auth !== null) {
            $guzzleRequest->setAuth(
                $this->auth['user'],
                $this->auth['password'],
                $this->auth['type']
            );
        }

        foreach ($this->getCookieJar()->allRawValues($request->getUri()) as $name => $value) {
            $guzzleRequest->addCookie($name, $value);
        }

        if ('POST' == $request->getMethod()) {
            $this->addPostFiles($guzzleRequest, $request->getFiles());
        }

        $guzzleRequest->getParams()->set('redirect.disable', true);
        $curlOptions = $guzzleRequest->getCurlOptions();

        if (!$curlOptions->get(CURLOPT_TIMEOUT)) {
            $curlOptions->set(CURLOPT_TIMEOUT, 30);
        }

        // Let BrowserKit handle redirects
        try {
            $response = $guzzleRequest->send();
        } catch (CurlException $e) {
            if (!strpos($e->getMessage(), 'redirects')) {
                throw $e;
            }

            $response = $e->getResponse();
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
        }

        return $this->createResponse($response);
    }

    protected function addPostFiles($request, array $files, $arrayName = '')
    {
        if (!$request instanceof EntityEnclosingRequestInterface) {
            return;
        }

        foreach ($files as $name => $info) {
            if (!empty($arrayName)) {
                $name = $arrayName . '[' . $name . ']';
            }

            if (is_array($info)) {
                if (isset($info['tmp_name'])) {
                    if ('' !== $info['tmp_name']) {
                        $request->addPostFile($name, $info['tmp_name']);
                    } else {
                        continue;
                    }
                } else {
                    $this->addPostFiles($request, $info, $name);
                }
            } else {
                $request->addPostFile($name, $info);
            }
        }
    }

    protected function createResponse(GuzzleResponse $response)
    {
        return new Response($response->getBody(true), $response->getStatusCode(), $response->getHeaders()->getAll());
    }
}
