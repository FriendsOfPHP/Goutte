<?php

namespace Goutte;

use Symfony\Component\BrowserKit\Client as BaseClient;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\BrowserKit\Response;

use Buzz\Browser as BuzzBrowser;
use Buzz\Client\Curl as BuzzCurlAdapter;
use Buzz\Message\Request as BuzzRequest;
use Buzz\Message\Response as BuzzResponse;

/*
 * This file is part of the Goutte package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Client.
 *
 * @package Goutte
 * @author  Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author  Christian Eikermann <christian@chrisdev.de>
 */
class Client extends BaseClient
{
    protected $browser = null;

    protected function doRequest($request)
    {
        $buzzRequest = $this->createRequest($request);
        
        $buzzResponse = $this->getBrowser()->send($buzzRequest);
        
        return $this->createResponse($buzzResponse);
    }

    protected function createRequest(Request $request)
    {
        $buzzRequest = new BuzzRequest();
        $buzzRequest->setMethod($request->getMethod());
        $buzzRequest->fromUrl($request->getUri());
        $buzzRequest->addHeaders($request->getServer());
        $buzzRequest->setContent($request->getContent());
        
        if ($request->getMethod() == 'POST') {
            $content = http_build_query($request->getParameters());
            $buzzRequest->setContent($content);
        } else {
            $url = $request->getUri();
            $parameters = $request->getParameters();
            if (count($parameters) > 0) {
                $url .= '?'.http_build_query($request->getParameters());
            }
            
            $buzzRequest->fromUrl($url);
        }
        
        return $buzzRequest;
    }

    protected function createResponse(BuzzResponse $response)
    {
        return new Response($response->getContent(), $response->getStatusCode(), $response->getHeaders());
    }
    
    public function getBrowser()
    {
        if (!$this->browser) {
            $this->browser = new BuzzBrowser(new BuzzCurlAdapter());
        }
        
        return $this->browser;
    }
}
