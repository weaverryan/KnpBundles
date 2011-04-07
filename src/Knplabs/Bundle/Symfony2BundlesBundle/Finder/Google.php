<?php

namespace Knplabs\Bundle\Symfony2BundlesBundle\Finder;

use Symfony\Component\DomCrawler\Crawler;
use Goutte\Client;

/**
 * Finds github repositories using Google
 *
 * @package Symfony2Bundles
 */
class Google implements FinderInterface
{
    const ENDPOINT         = 'http://www.google.com/search';
    const PARAMETER_QUERY  = 'q';
    const PARAMETER_START  = 'start';
    const RESULTS_PER_PAGE = 10;

    private $client;
    private $query;
    private $limit;

    /**
     * Construct
     *
     * @param  string $query
     */
    public function __construct(Client $client, $query = null, $limit = 100)
    {
        $this->client = $client;
        $this->query = $query;
        $this->limit = $limit;
    }

    /**
     * Defines the query
     *
     * @param  string $query
     */
    public function setQuery($query)
    {
        $this->query = $query;
    }

    /**
     * Defines the limit of results to fetch
     *
     * @param  integer $limit
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;
    }

    /**
     * {@inheritDoc}
     */
    public function find()
    {
        if (empty($this->query)) {
            throw new \LogicException('You must specify a query to find repositories.');
        }

        $repositories = array();
        foreach (range(1, $this->getNumPages()) as $page) {
            $page = $this->getNumPages();

            $repositories = array_merge(
                $repositories,
                $extract
            );
        }

        return array_unique($repositories);
    }

    /**
     * Returns the URL to perform the search
     *
     * @param  integer $page The page number (default 1)
     *
     * @return string
     */
    private function buildUrl($page)
    {
        $params = array();
        $params[self::PARAMETER_QUERY] = $this->query;

        if ($page > 1) {
            $params[self::PARAMETER_START] = self::RESULTS_PER_PAGE * ($page - 1);
        }

        return self::ENDPOINT . '?' . http_build_query($params);
    }

    /**
     * Finds the repositories of the specified page url
     */
    private function findPage($page)
    {
        $repositories = array();
        $crawler = $this->client->request('GET', $this->buildUrl($page));
        $urls = $this->extractUrlsFromCrawler($crawler);

        foreach ($urls as $url) {
            $repository = $this->extractRepositoryFromUrl($url);
            if (null !== $repository && !in_array($repositories, $repository)) {
                $repositories[] = $repository;
            }
        }

        return $repositories;
    }

    /**
     * Extracts the urls from the given google results crawler
     *
     * @param  Crawler $crawler
     *
     * @return array
     */
    private function extractPageUrls(Crawler $crawler)
    {
        return $crawler->filter('#search h3 a')->extract('href');
    }

    /**
     * Returns the github repository extracted from the given URL
     *
     * @param  string $url
     *
     * @return string or NULL if the URL does not contain any repository
     */
    private function extractUrlRepository($url)
    {
        if (preg_match('/https?:\/\/(www.)?github.com\/(?<username>\w+)\/(?<repository>\w+)/', $url, $matches)) {
            return $matches['username'] . '/' . $matches['repository'];
        }

        return null;
    }

    /**
     * Returns the number of pages to fetch depending on the limit
     *
     * @return  integer
     */
    public function getNumPages()
    {
        return ceil($this->limit / self::RESULTS_PER_PAGE);
    }
}
