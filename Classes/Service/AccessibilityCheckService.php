<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Service;

use Maispace\MaiAccessibility\Check\CheckInterface;
use Maispace\MaiAccessibility\Check\CheckResult;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Site\SiteFinder;

final class AccessibilityCheckService
{
    /** @var CheckInterface[] */
    private array $checks = [];

    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly RequestFactory $requestFactory,
        private readonly SiteFinder $siteFinder,
    ) {}

    public function addCheck(CheckInterface $check): void
    {
        $this->checks[$check->getIdentifier()] = $check;
    }

    /**
     * @return CheckResult[]
     */
    public function checkPage(int $pageUid): array
    {
        $html = $this->fetchPageHtml($pageUid);
        if ($html === null) {
            return [];
        }

        $results = [];
        foreach ($this->checks as $check) {
            array_push($results, ...$check->check($html, $pageUid));
        }

        return $results;
    }

    /**
     * @param int[] $pageUids
     * @return array<int, CheckResult[]>
     */
    public function checkPages(array $pageUids): array
    {
        $allResults = [];
        foreach ($pageUids as $uid) {
            $results = $this->checkPage($uid);
            if ($results !== []) {
                $allResults[$uid] = $results;
            }
        }
        return $allResults;
    }

    private function fetchPageHtml(int $pageUid): ?string
    {
        try {
            $site = $this->siteFinder->getSiteByPageId($pageUid);
            $uri = $site->getRouter()->generateUri($pageUid);
            $response = $this->requestFactory->request((string)$uri);
            if ($response->getStatusCode() !== 200) {
                return null;
            }
            return (string)$response->getBody();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, string>
     */
    public function getRegisteredCheckIdentifiers(): array
    {
        return array_keys($this->checks);
    }
}
