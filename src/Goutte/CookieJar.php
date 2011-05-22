<?php

namespace Goutte;
use Goutte\Cookie as GoutteCookie;
use Symfony\Component\BrowserKit\CookieJar as SymfonyCookieJar;

class CookieJar extends SymfonyCookieJar
{
    /**
     * Sets a cookie.
     *
     * @param Cookie $cookie A Cookie instance
     *
     * @api
     */
    public function set(GoutteCookie $cookie)
    {
        parent::set($cookie);
    }

    /**
     * Updates the cookie jar from a Response object.
     *
     * @param Response $response A Response object
     * @param string   $url    The base URL
     */
    public function updateFromResponse(\Symfony\Component\BrowserKit\Response $response, $uri = null)
    {
        foreach ($response->getHeader('Set-Cookie', false) as $cookie) {
            $this->set(GoutteCookie::fromString($cookie), $uri);
        }
    }

    /**
     * Returns not yet expired cookie values for the given URI.
     *
     * @param string $uri A URI
     * @param bool   $is_raw_value returnes raw value or decoded value
     *
     * @return array An array of cookie values
     */
    public function allValues($uri, $returns_raw_value = false)
    {
        $this->flushExpiredCookies();

        $parts = parse_url($uri);

        $cookies = array();
        foreach ($this->cookieJar as $cookie) {
            if ($cookie->getDomain() && $cookie->getDomain() != substr($parts['host'], -strlen($cookie->getDomain()))) {
                continue;
            }

            if ($cookie->getPath() != substr($parts['path'], 0, strlen($cookie->getPath()))) {
                continue;
            }

            if ($cookie->isSecure() && 'https' != $parts['scheme']) {
                continue;
            }

            if ($returns_raw_value) {
                $cookies[$cookie->getName()] = $cookie->getRawValue();
            } else {
                $cookies[$cookie->getName()] = $cookie->getValue();
            }
        }

        return $cookies;
    }
}
