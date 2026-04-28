<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Service;

use Maispace\MaiAccessibility\Check\CheckInterface;
use Maispace\MaiAccessibility\Check\CheckResult;
use TYPO3\CMS\Core\Database\ConnectionPool;

final class AccessibilityCheckService
{
    /** @var CheckInterface[] */
    private array $checks = [];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
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
     * @return array<string, string>
     */
    public function getRegisteredCheckIdentifiers(): array
    {
        return array_keys($this->checks);
    }

    private function buildContentHtml(int $pageUid): string
    {
        $parts = [];

        $contentRows = $this->fetchContentElements($pageUid);
        foreach ($contentRows as $row) {
            if (!empty($row['header'])) {
                $parts[] = sprintf('<h2>%s</h2>', htmlspecialchars((string)$row['header']));
            }
            if (!empty($row['subheader'])) {
                $parts[] = sprintf('<h3>%s</h3>', htmlspecialchars((string)$row['subheader']));
            }
            if (!empty($row['bodytext'])) {
                $parts[] = (string)$row['bodytext'];
            }
        }

        $imageAltTexts = $this->fetchImageAltTexts($pageUid);
        foreach ($imageAltTexts as $ref) {
            $alt = $ref['alternative'] ?? null;
            $src = $ref['identifier'] ?? 'image';
            if ($alt === null) {
                $parts[] = sprintf('<img src="%s">', htmlspecialchars((string)$src));
            } else {
                $parts[] = sprintf('<img src="%s" alt="%s">', htmlspecialchars((string)$src), htmlspecialchars((string)$alt));
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

    private function fetchImageAltTexts(int $pageUid): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        return $qb
            ->select('r.alternative', 'f.identifier')
            ->from('sys_file_reference', 'r')
            ->join('r', 'sys_file', 'f', $qb->expr()->eq('r.uid_local', 'f.uid'))
            ->join('r', 'tt_content', 'c', $qb->expr()->eq('r.uid_foreign', 'c.uid'))
            ->where(
                $qb->expr()->eq('c.pid', $qb->createNamedParameter($pageUid, \Doctrine\DBAL\ParameterType::INTEGER)),
                $qb->expr()->eq('r.deleted', 0),
                $qb->expr()->eq('r.hidden', 0),
                $qb->expr()->eq('c.deleted', 0),
                $qb->expr()->eq('c.hidden', 0),
                $qb->expr()->eq('r.tablenames', $qb->createNamedParameter('tt_content')),
                $qb->expr()->eq('r.fieldname', $qb->createNamedParameter('image')),
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
