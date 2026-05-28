<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Tests\Unit\Service;

use Maispace\MaiAccessibility\Service\FrontendHtmlFetcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;

final class FrontendHtmlFetcherTest extends TestCase
{
    private function makeFetcher(
        SiteFinder $siteFinder,
        RequestFactory $requestFactory,
    ): FrontendHtmlFetcher {
        return new FrontendHtmlFetcher(
            $siteFinder,
            $requestFactory,
            $this->createMock(LoggerInterface::class),
        );
    }

    #[Test]
    public function fetchHtmlForPageReturnsEmptyStringWhenNoSiteFound(): void
    {
        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')
            ->willThrowException(new SiteNotFoundException('no site'));

        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->expects(self::never())->method('request');

        $fetcher = $this->makeFetcher($siteFinder, $requestFactory);
        self::assertSame('', $fetcher->fetchHtmlForPage(42));
    }

    #[Test]
    public function fetchHtmlForPageReturnsEmptyStringOnHttpError(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('__toString')->willReturn('https://example.com/test');

        $router = $this->createMock(RouterInterface::class);
        $router->method('generateUri')->willReturn($uri);

        $language = $this->createMock(SiteLanguage::class);

        $site = $this->createMock(Site::class);
        $site->method('getDefaultLanguage')->willReturn($language);
        $site->method('getRouter')->willReturn($router);

        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);

        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->method('request')->willReturn($response);

        $fetcher = $this->makeFetcher($siteFinder, $requestFactory);
        self::assertSame('', $fetcher->fetchHtmlForPage(42));
    }

    #[Test]
    public function fetchHtmlForPageReturnsBodyOnSuccess(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('__toString')->willReturn('https://example.com/page');

        $router = $this->createMock(RouterInterface::class);
        $router->method('generateUri')->willReturn($uri);

        $language = $this->createMock(SiteLanguage::class);

        $site = $this->createMock(Site::class);
        $site->method('getDefaultLanguage')->willReturn($language);
        $site->method('getRouter')->willReturn($router);

        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $body = $this->createMock(StreamInterface::class);
        $body->method('__toString')->willReturn('<html><body><h1>Hello</h1></body></html>');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($body);

        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->method('request')->willReturn($response);

        $fetcher = $this->makeFetcher($siteFinder, $requestFactory);
        $html = $fetcher->fetchHtmlForPage(42);
        self::assertStringContainsString('<h1>Hello</h1>', $html);
    }

    #[Test]
    public function fetchHtmlForPageReturnsEmptyStringWhenRequestThrows(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('__toString')->willReturn('https://example.com/page');

        $router = $this->createMock(RouterInterface::class);
        $router->method('generateUri')->willReturn($uri);

        $language = $this->createMock(SiteLanguage::class);

        $site = $this->createMock(Site::class);
        $site->method('getDefaultLanguage')->willReturn($language);
        $site->method('getRouter')->willReturn($router);

        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->method('request')->willThrowException(new \RuntimeException('connection refused'));

        $fetcher = $this->makeFetcher($siteFinder, $requestFactory);
        self::assertSame('', $fetcher->fetchHtmlForPage(42));
    }
}
