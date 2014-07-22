Goutte, a simple PHP Web Scraper
================================

Goutte is a screen scraping and web crawling library for PHP.

Goutte provides a nice API to crawl websites and extract data from the HTML/XML
responses.

Requirements
------------

Goutte depends on PHP 5.4+ and Guzzle 4+.

.. tip::

    If you need support for PHP 5.3 or Guzzle 3, use Goutte 1.0.6.

Installation
------------

Add ``fabpot/goutte`` as a require dependency in your ``composer.json`` file:

.. code-block:: bash

    php composer.phar require fabpot/goutte:~2.0

.. tip::

    You can also download the `Goutte.phar`_ file:

    .. code-block:: php

        require_once '/path/to/goutte.phar';

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
    $crawler = $client->request('GET', 'http://www.symfony.com/blog/');

The method returns a ``Crawler`` object
(``Symfony\Component\DomCrawler\Crawler``).

Fine-tune cURL options:

.. code-block:: php

    $client->getClient()->setDefaultOption('config/curl/'.CURLOPT_TIMEOUT, 60);

Click on links:

.. code-block:: php

    // Click on the "Security Advisories" link
    $link = $crawler->selectLink('Security Advisories')->link();
    $crawler = $client->click($link);

Extract data:

.. code-block:: php

    // Get the latest post in this category and display the titles
    $crawler->filter('h2.post > a')->each(function ($node) {
        print $node->text()."\n";
    });

Submit forms:

.. code-block:: php

    $crawler = $client->request('GET', 'http://github.com/');
    $crawler = $client->click($crawler->selectLink('Sign in')->link());
    $form = $crawler->selectButton('Sign in')->form();
    $crawler = $client->submit($form, array('login' => 'fabpot', 'password' => 'xxxxxx'));
    $crawler->filter('.flash-error')->each(function ($node) {
        print $node->text()."\n";
    });

More Information
----------------

Read the documentation of the BrowserKit and DomCrawler Symfony Components for
more information about what you can do with Goutte.

Technical Information
---------------------

Goutte is a thin wrapper around the following fine PHP libraries:

* Symfony Components: BrowserKit, CssSelector and DomCrawler;

*  `Guzzle`_ HTTP Component.

License
-------

Goutte is licensed under the MIT license.

.. _`Composer`:    http://getcomposer.org
.. _`Goutte.phar`: http://get.sensiolabs.org/goutte.phar
.. _`Guzzle`:      http://docs.guzzlephp.org
