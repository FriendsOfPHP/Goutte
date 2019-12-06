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

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\BrowserKit\Response;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Michael Dowling <michael@guzzlephp.org>
 * @author Charles Sarrazin <charles@sarraz.in>
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class Client extends AbstractBrowser
{
    protected $client;

    private $headers = [];
    private $auth;

    public function setClient(GuzzleClientInterface $client)
    {
        $this->client = $client;

        if (null !== $this->getServerParameter('HTTP_HOST', null) || null === $baseUri = $client->getConfig('base_uri')) {
            return $this;
        }

        $path = $baseUri->getPath();
        if ('' !== $path && '/' !== $path) {
            throw new \InvalidArgumentException('Setting a path in the Guzzle "base_uri" config option is not supported by Goutte yet.');
        }

        if (null === $this->getServerParameter('HTTPS', null) && 'https' === $baseUri->getScheme()) {
            $this->setServerParameter('HTTPS', 'on');
        }

        $host = $baseUri->getHost();
        if (null !== $port = $baseUri->getPort()) {
            $host .= ":$port";
        }

        $this->setServerParameter('HTTP_HOST', $host);

        return $this;
    }

    public function getClient()
    {
        if (!$this->client) {
            $this->client = new GuzzleClient(['allow_redirects' => false, 'cookies' => true]);
        }

        return $this->client;
    }

    public function setHeader($name, $value)
    {
        $this->headers[strtolower($name)] = $value;

        return $this;
    }

    public function removeHeader($name)
    {
        unset($this->headers[strtolower($name)]);
    }

    public function resetHeaders()
    {
        $this->headers = [];

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

    public function setAuth($user, $password = '', $type = 'basic')
    {
        $this->auth = [$user, $password, $type];

        return $this;
    }

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
        $headers = [];
        foreach ($request->getServer() as $key => $val) {
            $key = strtolower(str_replace('_', '-', $key));
            $contentHeaders = ['content-length' => true, 'content-md5' => true, 'content-type' => true];
            if (0 === strpos($key, 'http-')) {
                $headers[substr($key, 5)] = $val;
            }
            // CONTENT_* are not prefixed with HTTP_
            elseif (isset($contentHeaders[$key])) {
                $headers[$key] = $val;
            }
        }

        $cookies = CookieJar::fromArray(
            $this->getCookieJar()->allRawValues($request->getUri()),
            parse_url($request->getUri(), PHP_URL_HOST)
        );

        $requestOptions = [
            'cookies' => $cookies,
            'allow_redirects' => false,
            'auth' => $this->auth,
        ];

        if (!\in_array($request->getMethod(), ['GET', 'HEAD'])) {
            if (null !== $content = $request->getContent()) {
                $requestOptions['body'] = $content;
            } else {
                if ($files = $request->getFiles()) {
                    $requestOptions['multipart'] = [];

                    $this->addPostFields($request->getParameters(), $requestOptions['multipart']);
                    $this->addPostFiles($files, $requestOptions['multipart']);
                } else {
                    $requestOptions['form_params'] = $request->getParameters();
                }
            }
        }

        if (!empty($headers)) {
            $requestOptions['headers'] = $headers;
        }

        $method = $request->getMethod();
        $uri = $request->getUri();

        foreach ($this->headers as $name => $value) {
            $requestOptions['headers'][$name] = $value;
        }

        // Let BrowserKit handle redirects
        try {
            $response = $this->getClient()->request($method, $uri, $requestOptions);
        } catch (RequestException $e) {
            $response = $e->getResponse();
            if (null === $response) {
                throw $e;
            }
        }

        return $this->createResponse($response);
    }

    protected function addPostFiles(array $files, array &$multipart, $arrayName = '')
    {
        if (empty($files)) {
            return;
        }

        foreach ($files as $name => $info) {
            if (!empty($arrayName)) {
                $name = $arrayName.'['.$name.']';
            }

            $file = [
                'name' => $name,
            ];

            if (\is_array($info)) {
                if (isset($info['tmp_name'])) {
                    if ('' !== $info['tmp_name']) {
                        $file['contents'] = fopen($info['tmp_name'], 'r');
                        if (isset($info['name'])) {
                            $file['filename'] = $info['name'];
                        }
                    } else {
                        continue;
                    }
                } else {
                    $this->addPostFiles($info, $multipart, $name);
                    continue;
                }
            } else {
                $file['contents'] = fopen($info, 'r');
            }

            $multipart[] = $file;
        }
    }

    public function addPostFields(array $formParams, array &$multipart, $arrayName = '')
    {
        foreach ($formParams as $name => $value) {
            if (!empty($arrayName)) {
                $name = $arrayName.'['.$name.']';
            }

            if (\is_array($value)) {
                $this->addPostFields($value, $multipart, $name);
            } else {
                $multipart[] = [
                    'name' => $name,
                    'contents' => $value,
                ];
            }
        }
    }

    protected function createResponse(ResponseInterface $response)
    {
        return new Response((string) $response->getBody(), $response->getStatusCode(), $response->getHeaders());
    }
}
