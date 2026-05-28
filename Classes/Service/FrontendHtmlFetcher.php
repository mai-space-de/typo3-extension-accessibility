<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Service;

use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;

/**
 * Fetches the rendered frontend HTML for a given TYPO3 page UID.
 *
 * Uses TYPO3's SiteFinder to resolve the canonical URL for the page and
 * RequestFactory to perform an internal HTTP GET. Falls back to an empty
 * string when the page cannot be resolved or the request fails, so the
 * caller can gracefully degrade to DB-synthetic content.
 */
final class FrontendHtmlFetcher implements FrontendHtmlFetcherInterface
{
    public function __construct(
        private readonly SiteFinder $siteFinder,
        private readonly RequestFactory $requestFactory,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Returns the rendered HTML body for the given page UID, or an empty
     * string if the page URL cannot be resolved or the HTTP request fails.
     */
    public function fetchHtmlForPage(int $pageUid): string
    {
        $url = $this->resolvePageUrl($pageUid);
        if ($url === null) {
            return '';
        }

        return $this->fetchUrl((string) $url);
    }

    private function resolvePageUrl(int $pageUid): ?UriInterface
    {
        try {
            $site = $this->siteFinder->getSiteByPageId($pageUid);
        } catch (SiteNotFoundException $e) {
            $this->logger->debug(
                'mai_accessibility: no site found for page {pageUid}',
                ['pageUid' => $pageUid, 'exception' => $e],
            );
            return null;
        }

        $language = $site->getDefaultLanguage();

        return $site->getRouter()->generateUri(
            $pageUid,
            ['_language' => $language],
        );
    }

    private function fetchUrl(string $url): string
    {
        try {
            $response = $this->requestFactory->request($url, 'GET', [
                'headers' => [
                    'Accept' => 'text/html',
                    'User-Agent' => 'TYPO3 mai_accessibility checker',
                ],
                'timeout' => 10,
                'allow_redirects' => true,
                'http_errors' => false,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                $this->logger->debug(
                    'mai_accessibility: frontend fetch returned HTTP {status} for {url}',
                    ['status' => $statusCode, 'url' => $url],
                );
                return '';
            }

            return (string) $response->getBody();
        } catch (\Throwable $e) {
            $this->logger->warning(
                'mai_accessibility: frontend fetch failed for {url}: {message}',
                ['url' => $url, 'message' => $e->getMessage(), 'exception' => $e],
            );
            return '';
        }
    }
}
