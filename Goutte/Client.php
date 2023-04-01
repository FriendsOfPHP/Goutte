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

use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated Use Symfony\Component\BrowserKit\HttpBrowser directly instead
 */
class Client extends HttpBrowser
{
    public function __construct(HttpClientInterface $client = null, History $history = null, CookieJar $cookieJar = null)
    {
        trigger_deprecation('fabpot/goutte', '4.0', 'The "%s" class is deprecated, use "%s" instead.', __CLASS__, HttpBrowser::class);

        parent::__construct($client, $history, $cookieJar);
    }
}
