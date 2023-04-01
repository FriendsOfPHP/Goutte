Goutte, a simple PHP Web Scraper
================================

Goutte is a screen scraping and web crawling library for PHP.

Goutte provides a nice API to crawl websites and extract data from the HTML/XML
responses.

**WARNING**: This library is deprecated. As of v4, Goutte became a simple proxy
to the `HttpBrowser class
<https://symfony.com/doc/current/components/browser_kit.html#making-external-http-requests>`_
from the `Symfony BrowserKit <https://symfony.com/browser-kit>`_ component. To
migrate, replace ``Goutte\\Client`` by
``Symfony\\Component\\BrowserKit\\HttpBrowser`` in your code.

Requirements
------------

Goutte depends on PHP 7.1+.

Installation
------------

Add ``fabpot/goutte`` as a require dependency in your ``composer.json`` file:

.. code-block:: bash

    composer require fabpot/goutte

Usage
-----

Create a Goutte Client instance (which extends
``Symfony\Component\BrowserKit\HttpBrowser``):

.. code-block:: php

    use Goutte\Client;

    $client = new Client();

Make requests with the ``request()`` method:

.. code-block:: php

    // Go to the symfony.com website
    $crawler = $client->request('GET', 'https://www.symfony.com/blog/');

The method returns a ``Crawler`` object
(``Symfony\Component\DomCrawler\Crawler``).

To use your own HTTP settings, you may create and pass an HttpClient
instance to Goutte. For example, to add a 60 second request timeout:

.. code-block:: php

    use Goutte\Client;
    use Symfony\Component\HttpClient\HttpClient;

    $client = new Client(HttpClient::create(['timeout' => 60]));

Click on links:

.. code-block:: php

    // Click on the "Security Advisories" link
    $link = $crawler->selectLink('Security Advisories')->link();
    $crawler = $client->click($link);

Extract data:

.. code-block:: php

    // Get the latest post in this category and display the titles
    $crawler->filter('h2 > a')->each(function ($node) {
        print $node->text()."\n";
    });

Submit forms:

.. code-block:: php

    $crawler = $client->request('GET', 'https://github.com/');
    $crawler = $client->click($crawler->selectLink('Sign in')->link());
    $form = $crawler->selectButton('Sign in')->form();
    $crawler = $client->submit($form, ['login' => 'fabpot', 'password' => 'xxxxxx']);
    $crawler->filter('.flash-error')->each(function ($node) {
        print $node->text()."\n";
    });

More Information
----------------

Read the documentation of the `BrowserKit`_, `DomCrawler`_, and `HttpClient`_
Symfony Components for more information about what you can do with Goutte.

Pronunciation
-------------

Goutte is pronounced ``goot`` i.e. it rhymes with ``boot`` and not ``out``.

Technical Information
---------------------

Goutte is a thin wrapper around the following Symfony Components:
`BrowserKit`_, `CssSelector`_, `DomCrawler`_, and `HttpClient`_.

License
-------

Goutte is licensed under the MIT license.

.. _`Composer`: https://getcomposer.org
.. _`BrowserKit`: https://symfony.com/components/BrowserKit
.. _`DomCrawler`: https://symfony.com/doc/current/components/dom_crawler.html
.. _`CssSelector`: https://symfony.com/doc/current/components/css_selector.html
.. _`HttpClient`: https://symfony.com/doc/current/components/http_client.html
