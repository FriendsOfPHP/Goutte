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

use GuzzleHttp\Psr7\Request as Psr7Request;
use Http\Client\Common\Plugin\AuthenticationPlugin;
use Http\Client\Common\Plugin\CookiePlugin;
use Http\Client\Common\Plugin\HeaderSetPlugin;
use Http\Client\Common\PluginClient;
use Http\Client\Exception\HttpException;
use Http\Client\HttpClient;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\StreamFactoryDiscovery;
use Http\Message\Authentication;
use Http\Message\Cookie;
use Http\Message\CookieJar;
use Http\Message\MultipartStream\MultipartStreamBuilder;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\BrowserKit\Client as BaseClient;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\BrowserKit\Response;

/**
 * Client.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author Michael Dowling <michael@guzzlephp.org>
 * @author Charles Sarrazin <charles@sarraz.in>
 */
class Client extends BaseClient
{
    /**
     * @var HttpClient
     */
    protected $adapter;

    /**
     * @var string[]
     */
    private $headers = array();

    /**
     * @var null|Authentication
     */
    private $auth = null;

    /**
     * @return HttpClient
     */
    public function getAdapter()
    {
        return $this->adapter ?: HttpClientDiscovery::find();
    }

    /**
     * @param HttpClient $adapter
     *
     * @return Client
     */
    public function setAdapter(HttpClient $adapter)
    {
        $this->adapter = $adapter;

        return $this;
    }

    /**
     * @param string $name
     * @param string $value
     *
     * @return Client
     */
    public function setHeader($name, $value)
    {
        $this->headers[strtolower($name)] = $value;

        return $this;
    }

    /**
     * @param string $name
     */
    public function removeHeader($name)
    {
        unset($this->headers[strtolower($name)]);
    }

    /**
     * @return Client
     */
    public function resetHeaders()
    {
        $this->headers = array();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function restart()
    {
        parent::restart();
        $this->resetAuth()
             ->resetHeaders();
    }

    /**
     * @param Authentication $auth
     *
     * @see http://docs.php-http.org/en/latest/message/authentication.html
     *
     * @return Client
     */
    public function setAuth(Authentication $auth)
    {
        $this->auth = $auth;

        return $this;
    }

    /**
     * @return Client
     */
    public function resetAuth()
    {
        $this->auth = null;

        return $this;
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    protected function doRequest($request)
    {
        // Let BrowserKit handle redirects
        try {
            $response = $this->createClient($request)->sendRequest($this->buildPsr7Request($request));
        } catch (HttpException $e) {
            $response = $e->getResponse();
            if (null === $response) {
                throw $e;
            }
        }

        return $this->createResponse($response);
    }

    /**
     * @param Request $request
     *
     * @return HttpClient
     */
    protected function createClient(Request $request)
    {
        $plugins = array();

        if ($this->auth) {
            $plugins[] = new AuthenticationPlugin($this->auth);
        }

        if ($this->headers) {
            $plugins[] = new HeaderSetPlugin($this->headers);
        }

        $plugins[] = new CookiePlugin($this->buildCookieJar($request));

        return new PluginClient($this->adapter, $plugins);
    }

    /**
     * @param ResponseInterface $response
     *
     * @return Response
     */
    protected function createResponse(ResponseInterface $response)
    {
        return new Response((string) $response->getBody(), $response->getStatusCode(), $response->getHeaders());
    }

    /**
     * @param Request $request
     *
     * @return CookieJar
     */
    protected function buildCookieJar(Request $request)
    {
        $jar = new CookieJar();
        $domain = parse_url($request->getUri(), PHP_URL_HOST);

        foreach ($this->getCookieJar()->allRawValues($request->getUri()) as $name => $value) {
            $jar->addCookie(new Cookie($name, $value, null, $domain));
        }

        return $jar;
    }

    /**
     * @param array $server
     *
     * @return array
     */
    protected function buildHeaders(array $server)
    {
        $headers = array();
        $contentHeaders = array('content-length' => true, 'content-md5' => true, 'content-type' => true);

        foreach ($server as $key => $val) {
            $key = strtolower(str_replace('_', '-', $key));
            if (0 === strpos($key, 'http-')) {
                $headers[substr($key, 5)] = $val;
            }
            // CONTENT_* are not prefixed with HTTP_
            elseif (isset($contentHeaders[$key])) {
                $headers[$key] = $val;
            }
        }

        return $headers;
    }

    /**
     * @param Request $request
     * @param array   $headers
     *
     * @return mixed
     */
    protected function buildBody(Request $request, array &$headers)
    {
        if (null !== $request->getContent()) {
            return $request->getContent();
        }

        if (!$request->getFiles()) {
            return $request->getParameters();
        }

        $builder = new MultipartStreamBuilder(StreamFactoryDiscovery::find());

        $this->addPostFields($builder, $request->getParameters());
        $this->addPostFiles($builder, $request->getFiles());

        $stream = $builder->build();
        $boundary = $builder->getBoundary();

        $headers['Content-Type'] = 'multipart/form-data; boundary="'.$boundary.'"';

        return $stream;
    }

    /**
     * @param MultipartStreamBuilder $builder
     * @param array                  $formParams
     * @param string                 $arrayName
     */
    protected function addPostFields(MultipartStreamBuilder $builder, array $formParams, $arrayName = '')
    {
        foreach ($formParams as $name => $value) {
            if (!empty($arrayName)) {
                $name = $arrayName.'['.$name.']';
            }

            if (is_array($value)) {
                $this->addPostFields($builder, $value, $name);
            } else {
                $builder->addResource($name, $value);
            }
        }
    }

    /**
     * @param MultipartStreamBuilder $builder
     * @param array                  $files
     * @param string                 $arrayName
     */
    protected function addPostFiles(MultipartStreamBuilder $builder, array $files, $arrayName = '')
    {
        if (empty($files)) {
            return;
        }

        foreach ($files as $name => $info) {
            if (!empty($arrayName)) {
                $name = $arrayName.'['.$name.']';
            }

            if (!is_array($info)) {
                $builder->addResource($name, fopen($info, 'r'));

                continue;
            }

            if (!isset($info['tmp_name'])) {
                $this->addPostFiles($builder, $info, $name);

                continue;
            }

            if (isset($info['tmp_name']) && '' !== $info['tmp_name']) {
                if (isset($info['name'])) {
                    $builder->addResource($name, fopen($info['tmp_name'], 'r'), array('filename' => $info['name']));
                } else {
                    $builder->addResource($name, fopen($info['tmp_name'], 'r'));
                }
            }
        }
    }

    /**
     * @param Request $request
     *
     * @return RequestInterface
     */
    protected function buildPsr7Request($request)
    {
        $headers = $this->buildHeaders($request->getServer());
        $body = !in_array($request->getMethod(), array('GET', 'HEAD')) ? $this->buildBody($request, $headers) : null;

        return new Psr7Request(
            $request->getMethod(),
            $request->getUri(),
            $headers,
            $body
        );
    }
}
