Goutte, a simple PHP Web Scraper
================================

Goutte is a screen scraping and web crawling library for PHP.

Goutte provides a nice API to crawl websites and extract data from the
HTML/XML responses.

Requirements
------------

Goutte works with PHP 5.3.3 or later.

Installation
------------

Installing Goutte is as easy as it can get. Download the [`Goutte.phar`][1]
file and you're done!

Usage
-----

Require the Goutte phar file to use Goutte in a script:

    require_once '/path/to/goutte.phar';

Create a Goutte Client instance (which extends
`Symfony\Component\BrowserKit\Client`):

    use Goutte\Client;

    $client = new Client();

Make requests with the `request()` method:

    $crawler = $client->request('GET', 'http://www.symfony-project.org/');

The method returns a `Crawler` object
(`Symfony\Component\DomCrawler\Crawler`).

Click on links:

    $link = $crawler->selectLink('Plugins')->link();
    $crawler = $client->click($link);

Submit forms:

    $form = $crawler->selectButton('sign in')->form();
    $crawler = $client->submit($form, array('signin[username]' => 'fabien', 'signin[password]' => 'xxxxxx'));

Extract data:

    $nodes = $crawler->filter('.error_list');
    if ($nodes->count())
    {
      die(sprintf("Authentication error: %s\n", $nodes->text()));
    }

    printf("Nb tasks: %d\n", $crawler->filter('#nb_tasks')->text());

More Information
----------------

Read the documentation of the BrowserKit and DomCrawler Symfony Components for
more information about what you can do with Goutte.

Technical Information
---------------------

Goutte is a thin wrapper around the following fine PHP libraries:

 * Symfony Components: BrowserKit, ClassLoader, CssSelector, DomCrawler,
   Finder, and Process

 * [Guzzle](http://www.guzzlephp.org) HTTP Component

License
-------

Goutte is licensed under the MIT license.

[1]: https://raw.github.com/fabpot/Goutte/master/goutte.phar

