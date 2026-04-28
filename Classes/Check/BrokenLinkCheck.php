<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Check;

use TYPO3\CMS\Core\Database\ConnectionPool;

final class BrokenLinkCheck implements CheckInterface
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function getIdentifier(): string
    {
        return 'broken_links';
    }

    public function check(string $html, int $pageUid): array
    {
        $results = [];

        $brokenLinks = $this->fetchBrokenLinks($pageUid);
        $scanExists = $this->linkvalidatorHasBeenRun($pageUid);

        if (!$scanExists) {
            $results[] = CheckResult::warning(
                $this->getIdentifier(),
                'No linkvalidator scan found for this page. Run the Link Validator module first to surface broken links.',
                '',
                $pageUid,
            );
            return $results;
        }

        foreach ($brokenLinks as $link) {
            $results[] = CheckResult::error(
                $this->getIdentifier(),
                sprintf('Broken link detected: %s', (string)($link['url'] ?? '(unknown)')),
                sprintf('Field: %s — Element UID: %s', (string)($link['field'] ?? ''), (string)($link['record_uid'] ?? '')),
                $pageUid,
            );
        }

        return $results;
    }

    private function fetchBrokenLinks(int $pageUid): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_linkvalidator_link');
        return $qb
            ->select('url', 'field', 'record_uid', 'link_type')
            ->from('tx_linkvalidator_link')
            ->where(
                $qb->expr()->eq('record_pid', $qb->createNamedParameter($pageUid, \Doctrine\DBAL\ParameterType::INTEGER)),
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    private function linkvalidatorHasBeenRun(int $pageUid): bool
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_linkvalidator_link');
        $count = $qb
            ->count('uid')
            ->from('tx_linkvalidator_link')
            ->where(
                $qb->expr()->eq('record_pid', $qb->createNamedParameter($pageUid, \Doctrine\DBAL\ParameterType::INTEGER)),
            )
            ->executeQuery()
            ->fetchOne();

        if ((int)$count > 0) {
            return true;
        }

        $historyQb = $this->connectionPool->getQueryBuilderForTable('tx_linkvalidator_link');
        $any = $historyQb
            ->count('uid')
            ->from('tx_linkvalidator_link')
            ->executeQuery()
            ->fetchOne();

        return (int)$any > 0;
    }
}
