Goutte, a simple PHP Web Scraper
================================

Goutte is a screen scraping and web crawling library for PHP.

Goutte provides a nice API to crawl websites and extract data from the HTML/XML
responses.

Requirements
------------

Goutte depends on PHP 7.1+ and Guzzle 6+.

.. tip::

    If you need support for PHP 5.5, use Goutte 3.x (latest `phar
    <https://github.com/FriendsOfPHP/Goutte/releases/download/v3.1.0/goutte-v3.1.0.phar>`_).

    If you need support for PHP 5.4 or Guzzle 4-5, use Goutte 2.x (latest `phar
    <https://github.com/FriendsOfPHP/Goutte/releases/download/v2.0.4/goutte-v2.0.4.phar>`_).

    If you need support for PHP 5.3 or Guzzle 3, use Goutte 1.x (latest `phar
    <https://github.com/FriendsOfPHP/Goutte/releases/download/v1.0.7/goutte-v1.0.7.phar>`_).

Installation
------------

Add ``fabpot/goutte`` as a require dependency in your ``composer.json`` file:

.. code-block:: bash

    composer require fabpot/goutte

Usage
-----

Create a Goutte Client instance (which extends
``Symfony\Component\BrowserKit\Client``):

.. code-block:: php

    use Goutte\Client;

    $client = new Client();

Make requests with the ``request()`` method:

.. code-block:: php

    // Go to the symfony.com website
    $crawler = $client->request('GET', 'https://www.symfony.com/blog/');

The method returns a ``Crawler`` object
(``Symfony\Component\DomCrawler\Crawler``).

To use your own Guzzle settings, you may create and pass a new Guzzle 6
instance to Goutte. For example, to add a 60 second request timeout:

.. code-block:: php

    use Goutte\Client;
    use GuzzleHttp\Client as GuzzleClient;
    
    $goutteClient = new Client();
    $guzzleClient = new GuzzleClient(array(
        'timeout' => 60,
    ));
    $goutteClient->setClient($guzzleClient);

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
    $crawler = $client->submit($form, array('login' => 'fabpot', 'password' => 'xxxxxx'));
    $crawler->filter('.flash-error')->each(function ($node) {
        print $node->text()."\n";
    });

More Information
----------------

Read the documentation of the `BrowserKit`_ and `DomCrawler`_ Symfony
Components for more information about what you can do with Goutte.

Pronunciation
-------------

Goutte is pronounced ``goot`` i.e. it rhymes with ``boot`` and not ``out``.

Technical Information
---------------------

Goutte is a thin wrapper around the following fine PHP libraries:

* Symfony Components: `BrowserKit`_, `CssSelector`_ and `DomCrawler`_;

*  `Guzzle`_ HTTP Component.

License
-------

Goutte is licensed under the MIT license.

.. _`Composer`: https://getcomposer.org
.. _`Guzzle`: http://docs.guzzlephp.org
.. _`BrowserKit`: https://symfony.com/components/BrowserKit
.. _`DomCrawler`: https://symfony.com/doc/current/components/dom_crawler.html
.. _`CssSelector`: https://symfony.com/doc/current/components/css_selector.html
