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

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Post\PostFile;
use Symfony\Component\BrowserKit\Client as BaseClient;
use Symfony\Component\BrowserKit\Response;

use GuzzleHttp\Message\Response as GuzzleResponse;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Client as GuzzleClient;

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
            $this->client = new GuzzleClient(array('allow_redirects' => true, 'cookies' => TRUE));
        }

        return $this->client;
    }

    public function setHeader($name, $value)
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function removeHeader($name)
    {
        unset($this->headers[$name]);
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

    public function resetAuth()
    {
        $this->auth = null;

        return $this;
    }

    protected function doRequest($request)
    {
        $headers = array();
        foreach ($request->getServer() as $key => $val) {
            $key = implode('-', array_map('ucfirst', explode('-', strtolower(str_replace(array('_', 'HTTP-'), array('-', ''), $key)))));
            if (!isset($headers[$key]) && !empty($val)) {
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

        if ($this->auth !== null) {
            $this->getClient()->setDefaultOption('auth', array(
                $this->auth['user'],
                $this->auth['password'],
                $this->auth['type']
            ));
        }

        $requestOptions = array(
          'body' => $body,
          'cookies' => $this->getCookieJar()->allRawValues($request->getUri()),
          'allow_redirects' => false,
          'timeout' => 30,
          'config' => array(
              'curl' => array(
                  'CURLOPT_TIMEOUT' => 30,
                  'CURLOPT_MAXREDIRS' => 0,
              ),
          )
        );

        if (!empty($headers)) {
          $requestOptions['headers'] = $headers;
        }

        $guzzleRequest = $this->getClient()->createRequest(
            $request->getMethod(),
            $request->getUri(),
            $requestOptions
        );

        foreach ($this->headers as $name => $value) {
            $guzzleRequest->setHeader($name, $value);
        }

        if ('POST' == $request->getMethod() || 'PUT' == $request->getMethod()) {
            $this->addPostFiles($guzzleRequest, $request->getFiles());
        }

        // Let BrowserKit handle redirects
        try {
            $response = $this->getClient()->send($guzzleRequest);
        } catch (RequestException $e) {
            $response = $e->getResponse();
        }

        return $this->createResponse($response);
    }

    protected function addPostFiles(RequestInterface $request, array $files, $arrayName = '')
    {

        foreach ($files as $name => $info) {
            if (!empty($arrayName)) {
                $name = $arrayName . '[' . $name . ']';
            }

            if (is_array($info)) {
                if (isset($info['tmp_name'])) {
                    if ('' !== $info['tmp_name']) {
                        $request->getBody()->addFile(new PostFile($name, fopen($info['tmp_name'], 'r')));
                    } else {
                        continue;
                    }
                } else {
                    $this->addPostFiles($request, $info, $name);
                }
            } else {
                $request->getBody()->addFile(new PostFile($name, fopen($info, 'r')));
            }
        }
    }

    protected function createResponse(GuzzleResponse $response)
    {
        $headers = $response->getHeaders();

        return new Response($response->getBody(true), $response->getStatusCode(), $headers);
    }
}
