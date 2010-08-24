<?php

namespace Goutte;

use Symfony\Component\BrowserKit\Client as BaseClient;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\BrowserKit\Response;

use Zend\Http\Client as ZendClient;
use Zend\Http\Response as ZendResponse;

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
            'adapter'      => 'Zend\\Http\\Client\\Adapter\\Socket',
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
        return new Response($response->getBody(), $response->getStatus(), $response->getHeaders());
    }

    protected function createZendClient()
    {
        return new ZendClient();
    }
}
