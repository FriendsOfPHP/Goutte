<?php

namespace Goutte;

use Symfony\Components\BrowserKit\Client as BaseClient;
use Symfony\Components\BrowserKit\History;
use Symfony\Components\BrowserKit\CookieJar;
use Symfony\Components\BrowserKit\Request;
use Symfony\Components\BrowserKit\Response;

use Zend\HTTP\Client as ZendClient;
use Zend\HTTP\Response\Response as ZendResponse;

/*
 * This file is part of the Goutte package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Client.
 *
 * @package Goutte
 * @author  Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Client extends BaseClient
{
    const VERSION = '0.1';

    protected $zendConfig;

    public function __construct(array $zendConfig = array(), array $server = array(), History $history = null, CookieJar $cookieJar = null)
    {
        $this->zendConfig = $zendConfig;

        parent::__construct($server, $history, $cookieJar);
    }

    protected function doRequest($request)
    {
        $client = $this->createClient($request);

        $response = $client->request();

        return $this->createResponse($response);
    }

    protected function createClient(Request $request)
    {
        $client = $this->createZendClient();
        $client->setUri($request->getUri());
        $client->setConfig(array_merge(array(
            'maxredirects' => 0,
            'timeout'      => 30,
            'useragent'    => $this->server['HTTP_USER_AGENT'],
            'adapter'      => 'Zend\\HTTP\\Client\\Adapter\\Socket',
            ), $this->zendConfig));
        $client->setMethod(strtoupper($request->getMethod()));

        if ('post' == $request->getMethod()) {
            $client->setParameterPost($request->getParameters());
        } else {
            $client->setParameterGet($request->getParameters());
        }

        foreach ($this->getCookieJar()->getValues($request->getUri()) as $name => $value) {
            $client->setCookie($name, $value);
        }

        return $client;
    }

    protected function createResponse(ZendResponse $response)
    {
        $headers = array($response->getHeader('Set-Cookie'));
        $cookies = array();
        foreach ($headers as $header) {
            if (!trim($header)) {
                continue;
            }

            $parts = explode(';', $header);
            $value = array_shift($parts);
            list($name, $value) = explode('=', trim($value));

            $cookies[$name] = array('value' => $value);

            foreach ($parts as $part) {
                list($key, $value) = explode('=', trim($part));
                $cookies[$name][$key] = $value;
            }
        }

        return new Response($response->getBody(), $response->getStatus(), $response->getHeaders(), $cookies);
    }

    protected function createZendClient()
    {
        return new ZendClient();
    }
}
