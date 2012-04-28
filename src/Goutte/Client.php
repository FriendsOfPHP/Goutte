<?php

namespace Goutte;

use Symfony\Component\BrowserKit\Client as BaseClient;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\BrowserKit\Response;

use Buzz\Browser as BuzzBrowser;
use Buzz\Client\Curl as BuzzCurlAdapter;
use Buzz\Client\ClientInterface as BuzzBrowserAdapter;
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
    /**
     * Buzz Browser
     * 
     * @var Buzz\Browser $browser
     */
    protected $browser = null;
    
    /**
     * Buzz browser adapter
     * 
     * @var Buzz\Client\ClientInterface
     */
    protected $browserAdapter = null;

    /**
     * Process the request
     * 
     * @param Symfony\Component\BrowserKit\Request $request The request object
     * 
     * @return Symfony\Component\BrowserKit\Response The response object
     */
    protected function doRequest(Request $request)
    {
        $buzzRequest = $this->createRequest($request);
        
        $buzzResponse = $this->getBrowser()->send($buzzRequest);
        
        return $this->createResponse($buzzResponse);
    }

    /**
     * Convert the Symfony request object into the Buzz request object
     * 
     * @param Symfony\Component\BrowserKit\Request $request The request object
     * 
     * @return Buzz\Message\Request
     */
    protected function createRequest(Request $request)
    {
        $buzzRequest = new BuzzRequest();
        $buzzRequest->setMethod($request->getMethod());
        $buzzRequest->fromUrl($request->getUri());
        $buzzRequest->addHeaders($request->getServer());
        $buzzRequest->setContent($request->getContent());
        
        if ($request->getMethod() == 'POST') {
            $buzzRequest->setContent($request->getParameters());
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

    
    /**
     * Convert the Buzz response object into the Symfony response object
     * 
     * @param Buzz\Message\Response $response The response object
     * 
     * @return \Symfony\Component\BrowserKit\Response 
     */
    protected function createResponse(BuzzResponse $response)
    {
        return new Response($response->getContent(), $response->getStatusCode(), $response->getHeaders());
    }
    
    /**
     * Return the Buzz browser instance
     * 
     * @return Buzz\Browser 
     */
    protected function getBrowser()
    {
        if (!$this->browser) {
            $this->browser = new BuzzBrowser($this->getBrowserAdapter());
        }
        
        return $this->browser;
    }
    
    /**
     * Set the Buzz browser instance
     * 
     * @param Buzz\Browser $browser 
     * 
     * @return void
     */
    public function setBrowser(BuzzBrowser $browser)
    {
        $this->browser = $browser;
    }
    
    /**
     * Return the Buzz browser adapter
     * 
     * @return Buzz\Client\ClientInterface 
     */
    protected function getBrowserAdapter()
    {
        if (!$this->browserAdapter) {
            $this->browserAdapter = new BuzzCurlAdapter();
        }
        
        return $this->browserAdapter;
    }

    /**
     * Set the Buzz browser adapter
     * 
     * @param Buzz\Client\ClientInterface $browserAdapter 
     */
    public function setBrowserAdapter(BuzzBrowserAdapter $browserAdapter)
    {
        $this->browserAdapter = $browserAdapter;
    }
    
    
}
