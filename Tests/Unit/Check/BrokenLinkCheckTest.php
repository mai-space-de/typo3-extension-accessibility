<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Tests\Unit\Check;

use Doctrine\DBAL\Result;
use Maispace\MaiAccessibility\Check\BrokenLinkCheck;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

final class BrokenLinkCheckTest extends TestCase
{
    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Build a QueryBuilder mock that returns $fetchAllResult from fetchAllAssociative().
     * Used for the SELECT query in fetchBrokenLinks().
     *
     * @param array<int, array<string, mixed>> $fetchAllResult
     */
    private function buildSelectQb(array $fetchAllResult): QueryBuilder
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn($fetchAllResult);

        $expr = $this->createMock(ExpressionBuilder::class);
        $expr->method('eq')->willReturnArgument(0);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('expr')->willReturn($expr);
        $qb->method('createNamedParameter')->willReturnCallback(static fn(mixed $v): string => (string) $v);
        $qb->method('executeQuery')->willReturn($result);

        return $qb;
    }

    /**
     * Build a QueryBuilder mock that returns $countResult from fetchOne().
     * Used for the COUNT queries in linkvalidatorHasBeenRun().
     */
    private function buildCountQb(int $countResult): QueryBuilder
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchOne')->willReturn($countResult);

        $expr = $this->createMock(ExpressionBuilder::class);
        $expr->method('eq')->willReturnArgument(0);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('count')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('expr')->willReturn($expr);
        $qb->method('createNamedParameter')->willReturnCallback(static fn(mixed $v): string => (string) $v);
        $qb->method('executeQuery')->willReturn($result);

        return $qb;
    }

    // ── Identifier ──────────────────────────────────────────────────────────

    #[Test]
    public function identifierIsBrokenLinks(): void
    {
        $pool = $this->createMock(ConnectionPool::class);
        $subject = new BrokenLinkCheck($pool);
        self::assertSame('broken_links', $subject->getIdentifier());
    }

    // ── No linkvalidator scan ────────────────────────────────────────────────

    #[Test]
    public function whenNoScanHasEverBeenRunReturnsOneWarning(): void
    {
        // fetchBrokenLinks: no links for page
        // linkvalidatorHasBeenRun: count by page = 0, count all = 0 → false
        $pool = $this->createMock(ConnectionPool::class);
        $pool->method('getQueryBuilderForTable')->willReturnOnConsecutiveCalls(
            $this->buildSelectQb([]),   // fetchBrokenLinks
            $this->buildCountQb(0),     // count by pageUid
            $this->buildCountQb(0),     // count all (history check)
        );

        $subject = new BrokenLinkCheck($pool);
        $results = $subject->check('', 1);

        self::assertCount(1, $results);
        self::assertSame('warning', $results[0]->severity);
        self::assertSame('broken_links', $results[0]->checkIdentifier);
    }

    #[Test]
    public function warningMessageMentionsLinkvalidator(): void
    {
        $pool = $this->createMock(ConnectionPool::class);
        $pool->method('getQueryBuilderForTable')->willReturnOnConsecutiveCalls(
            $this->buildSelectQb([]),
            $this->buildCountQb(0),
            $this->buildCountQb(0),
        );

        $subject = new BrokenLinkCheck($pool);
        $results = $subject->check('', 1);

        self::assertStringContainsStringIgnoringCase('linkvalidator', $results[0]->message);
    }

    // ── Scan ran but page is clean ───────────────────────────────────────────

    #[Test]
    public function whenScanExistsAndPageHasNoBrokenLinksReturnsEmpty(): void
    {
        // fetchBrokenLinks: no links for page
        // linkvalidatorHasBeenRun: count by page = 0, count all = 5 → true
        $pool = $this->createMock(ConnectionPool::class);
        $pool->method('getQueryBuilderForTable')->willReturnOnConsecutiveCalls(
            $this->buildSelectQb([]),   // fetchBrokenLinks
            $this->buildCountQb(0),     // count by pageUid
            $this->buildCountQb(5),     // count all → scan has been run
        );

        $subject = new BrokenLinkCheck($pool);
        $results = $subject->check('<p>All good</p>', 1);

        self::assertSame([], $results);
    }

    // ── Broken links found ───────────────────────────────────────────────────

    #[Test]
    public function whenBrokenLinksExistReturnsOneErrorPerLink(): void
    {
        $brokenLinks = [
            ['url' => 'https://gone.example.com', 'field' => 'bodytext', 'record_uid' => 10, 'link_type' => 'external'],
            ['url' => 'https://also-gone.example.com', 'field' => 'header_link', 'record_uid' => 11, 'link_type' => 'external'],
        ];

        // fetchBrokenLinks returns 2 links; linkvalidatorHasBeenRun count by page = 2 → true
        $pool = $this->createMock(ConnectionPool::class);
        $pool->method('getQueryBuilderForTable')->willReturnOnConsecutiveCalls(
            $this->buildSelectQb($brokenLinks),  // fetchBrokenLinks
            $this->buildCountQb(2),              // count by pageUid → scan exists
        );

        $subject = new BrokenLinkCheck($pool);
        $results = $subject->check('', 7);

        self::assertCount(2, $results);
        foreach ($results as $result) {
            self::assertSame('error', $result->severity);
            self::assertSame('broken_links', $result->checkIdentifier);
            self::assertSame(7, $result->pageUid);
        }
    }

    #[Test]
    public function brokenLinkErrorMessageContainsUrl(): void
    {
        $brokenLinks = [
            ['url' => 'https://dead.example.com', 'field' => 'bodytext', 'record_uid' => 99, 'link_type' => 'external'],
        ];

        $pool = $this->createMock(ConnectionPool::class);
        $pool->method('getQueryBuilderForTable')->willReturnOnConsecutiveCalls(
            $this->buildSelectQb($brokenLinks),
            $this->buildCountQb(1),
        );

        $subject = new BrokenLinkCheck($pool);
        $results = $subject->check('', 1);

        self::assertCount(1, $results);
        self::assertStringContainsString('https://dead.example.com', $results[0]->message);
    }

    #[Test]
    public function brokenLinkContextContainsFieldAndRecordUid(): void
    {
        $brokenLinks = [
            ['url' => 'https://x.example.com', 'field' => 'bodytext', 'record_uid' => 42, 'link_type' => 'external'],
        ];

        $pool = $this->createMock(ConnectionPool::class);
        $pool->method('getQueryBuilderForTable')->willReturnOnConsecutiveCalls(
            $this->buildSelectQb($brokenLinks),
            $this->buildCountQb(1),
        );

        $subject = new BrokenLinkCheck($pool);
        $results = $subject->check('', 1);

        self::assertStringContainsString('bodytext', $results[0]->context);
        self::assertStringContainsString('42', $results[0]->context);
    }

    #[Test]
    public function missingUrlFieldRendersAsUnknownInErrorMessage(): void
    {
        $brokenLinks = [
            ['field' => 'bodytext', 'record_uid' => 5],  // 'url' key missing
        ];

        $pool = $this->createMock(ConnectionPool::class);
        $pool->method('getQueryBuilderForTable')->willReturnOnConsecutiveCalls(
            $this->buildSelectQb($brokenLinks),
            $this->buildCountQb(1),
        );

        $subject = new BrokenLinkCheck($pool);
        $results = $subject->check('', 1);

        self::assertCount(1, $results);
        self::assertStringContainsString('(unknown)', $results[0]->message);
    }
}
