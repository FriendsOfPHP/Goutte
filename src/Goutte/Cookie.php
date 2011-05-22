<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Goutte;
use Symfony\Component\BrowserKit\Cookie as SymfonyCookie;

/**
 * Cookie represents an HTTP cookie.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class Cookie extends SymfonyCookie
{
    protected $rawValue = null;

    /**
     * Sets a cookie.
     *
     * @param  string  $name     The cookie name
     * @param  string  $value    The value of the cookie
     * @param  string  $expires  The time the cookie expires
     * @param  string  $path     The path on the server in which the cookie will be available on
     * @param  string  $domain   The domain that the cookie is available
     * @param  bool    $secure   Indicates that the cookie should only be transmitted over a secure HTTPS connection from the client
     * @param  bool    $httponly The cookie httponly flag
     * @param  bool    $isRawValue $value is raw or urldecoded
     *
     * @api
     */
    public function __construct($name, $value, $expires = null, $path = '/', $domain = '', $secure = false, $httponly = false, $isRawValue = false)
    {
        parent::__construct($name, $value, $expires, $path, $domain, $secure, $httponly);
        if ($isRawValue) {
            $this->rawValue = $value;
            $this->value    = urldecode($value);
        } else {
            $this->rawValue = urlencode($value);
        }
    }

    /**
     * Creates a Cookie instance from a Set-Cookie header value.
     *
     * @param string $cookie A Set-Cookie header value
     * @param string $url    The base URL
     *
     * @return Cookie A Cookie instance
     *
     * @api
     */
    static public function fromString($cookie, $url = null)
    {
        $parts = explode(';', $cookie);

        if (false === strpos($parts[0], '=')) {
            throw new \InvalidArgumentException('The cookie string "%s" is not valid.');
        }

        list($name, $value) = explode('=', array_shift($parts), 2);

        $values = array(
            'name'     => trim($name),
            'value'    => trim($value),
            'expires'  =>  null,
            'path'     => '/',
            'domain'   => '',
            'secure'   => false,
            'httponly' => false,
            'isRawValue' => true,
        );

        if (null !== $url) {
            if ((false === $parts = parse_url($url)) || !isset($parts['host']) || !isset($parts['path'])) {
                throw new \InvalidArgumentException(sprintf('The URL "%s" is not valid.', $url));
            }

            $values['domain'] = $parts['host'];
            $values['path'] = substr($parts['path'], 0, strrpos($parts['path'], '/'));
        }

        foreach ($parts as $part) {
            $part = trim($part);

            if ('secure' === strtolower($part)) {
                $values['secure'] = true;

                continue;
            }

            if ('httponly' === strtolower($part)) {
                $values['httponly'] = true;

                continue;
            }

            if (2 === count($elements = explode('=', $part, 2))) {
                if ('expires' === $elements[0]) {
                    if (false === $date = \DateTime::createFromFormat(static::DATE_FORMAT, $elements[1], new \DateTimeZone('UTC'))) {
                        throw new \InvalidArgumentException(sprintf('The expires part of cookie is not valid (%s).', $elements[1]));
                    }

                    $elements[1] = $date->getTimestamp();
                }

                $values[strtolower($elements[0])] = $elements[1];
            }
        }

        return new static(
            $values['name'],
            $values['value'],
            $values['expires'],
            $values['path'],
            $values['domain'],
            $values['secure'],
            $values['httponly'],
            $values['isRawValue']
        );
    }

    /**
     * Gets the value of the cookie.
     *
     * @return string The cookie value
     *
     * @api
     */
    public function getRawValue()
    {
        return $this->rawValue;
    }
}
