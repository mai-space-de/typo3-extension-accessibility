<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Service;

use Maispace\MaiAccessibility\Check\CheckInterface;
use Maispace\MaiAccessibility\Check\CheckResult;
use TYPO3\CMS\Core\Database\ConnectionPool;

final class AccessibilityCheckService
{
    /** @var array<string, CheckInterface> */
    private array $checks = [];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly FrontendHtmlFetcherInterface $frontendHtmlFetcher,
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
        $html = $this->buildContentHtml($pageUid);

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

    /**
     * @return list<string>
     */
    public function getRegisteredCheckIdentifiers(): array
    {
        return array_keys($this->checks);
    }

    private function buildContentHtml(int $pageUid): string
    {
        $frontendHtml = $this->frontendHtmlFetcher->fetchHtmlForPage($pageUid);
        if ($frontendHtml !== '') {
            return $frontendHtml;
        }

        $parts = [];

        $contentRows = $this->fetchContentElements($pageUid);
        foreach ($contentRows as $row) {
            if (!empty($row['header'])) {
                $parts[] = sprintf('<h2>%s</h2>', htmlspecialchars((string) $row['header']));
            }
            if (!empty($row['subheader'])) {
                $parts[] = sprintf('<h3>%s</h3>', htmlspecialchars((string) $row['subheader']));
            }
            if (!empty($row['bodytext'])) {
                $parts[] = (string) $row['bodytext'];
            }
        }

        $imageAltTexts = $this->fetchImageAltTexts($pageUid);
        foreach ($imageAltTexts as $ref) {
            $alt = $ref['alternative'];
            $src = $ref['identifier'];
            if ($alt === null) {
                $parts[] = sprintf('<img src="%s">', htmlspecialchars($src));
            } else {
                $parts[] = sprintf('<img src="%s" alt="%s">', htmlspecialchars($src), htmlspecialchars($alt));
            }
        }

        return implode("\n", $parts);
    }

    private function fetchContentElements(int $pageUid): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tt_content');
        return $qb
            ->select('header', 'subheader', 'bodytext')
            ->from('tt_content')
            ->where(
                $qb->expr()->eq('pid', $qb->createNamedParameter($pageUid, \Doctrine\DBAL\ParameterType::INTEGER)),
                $qb->expr()->eq('deleted', 0),
                $qb->expr()->eq('hidden', 0),
            )
            ->orderBy('sorting')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Effective alt matches FAL FileReference merge rules:
     * non-null reference.alternative wins (including ''); otherwise metadata.
     *
     * @return list<array{alternative: string|null, identifier: string}>
     */
    private function fetchImageAltTexts(int $pageUid): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $rows = $qb
            ->select('r.alternative', 'm.alternative AS meta_alternative', 'f.identifier')
            ->from('sys_file_reference', 'r')
            ->join('r', 'sys_file', 'f', 'r.uid_local = f.uid')
            ->leftJoin(
                'r',
                'sys_file_metadata',
                'm',
                'm.file = f.uid AND m.sys_language_uid IN (0, -1)',
            )
            ->join('r', 'tt_content', 'c', 'r.uid_foreign = c.uid')
            ->where(
                $qb->expr()->eq('c.pid', $qb->createNamedParameter($pageUid, \Doctrine\DBAL\ParameterType::INTEGER)),
                $qb->expr()->eq('r.deleted', 0),
                $qb->expr()->eq('r.hidden', 0),
                $qb->expr()->eq('c.deleted', 0),
                $qb->expr()->eq('c.hidden', 0),
                $qb->expr()->eq('r.tablenames', $qb->createNamedParameter('tt_content')),
                $qb->expr()->eq('f.type', $qb->createNamedParameter(\TYPO3\CMS\Core\Resource\FileType::IMAGE->value, \Doctrine\DBAL\ParameterType::INTEGER)),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            $referenceAlt = $row['alternative'] ?? null;
            $metaAlt = $row['meta_alternative'] ?? null;
            // FAL: only null on the reference falls back to metadata.
            $effective = $referenceAlt !== null ? (string) $referenceAlt : ($metaAlt !== null ? (string) $metaAlt : null);
            $result[] = [
                'alternative' => $effective,
                'identifier' => isset($row['identifier']) && (string) $row['identifier'] !== ''
                    ? (string) $row['identifier']
                    : 'image',
            ];
        }

        return $result;
    }
}
