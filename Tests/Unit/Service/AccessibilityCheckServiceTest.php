<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Tests\Unit\Service;

use Doctrine\DBAL\Result;
use Maispace\MaiAccessibility\Check\CheckInterface;
use Maispace\MaiAccessibility\Check\CheckResult;
use Maispace\MaiAccessibility\Service\AccessibilityCheckService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

final class AccessibilityCheckServiceTest extends TestCase
{
    private function buildServiceWithContentRows(array $contentRows, array $fileRefs = []): AccessibilityCheckService
    {
        $contentResult = $this->createMock(Result::class);
        $contentResult->method('fetchAllAssociative')->willReturn($contentRows);

        $fileResult = $this->createMock(Result::class);
        $fileResult->method('fetchAllAssociative')->willReturn($fileRefs);

        $exprBuilder = $this->createMock(ExpressionBuilder::class);
        $exprBuilder->method('eq')->willReturnArgument(0);

        $contentQb = $this->createMock(QueryBuilder::class);
        $contentQb->method('select')->willReturnSelf();
        $contentQb->method('from')->willReturnSelf();
        $contentQb->method('where')->willReturnSelf();
        $contentQb->method('orderBy')->willReturnSelf();
        $contentQb->method('expr')->willReturn($exprBuilder);
        $contentQb->method('createNamedParameter')->willReturnArgument(0);
        $contentQb->method('executeQuery')->willReturn($contentResult);

        $fileQb = $this->createMock(QueryBuilder::class);
        $fileQb->method('select')->willReturnSelf();
        $fileQb->method('from')->willReturnSelf();
        $fileQb->method('join')->willReturnSelf();
        $fileQb->method('where')->willReturnSelf();
        $fileQb->method('expr')->willReturn($exprBuilder);
        $fileQb->method('createNamedParameter')->willReturnArgument(0);
        $fileQb->method('executeQuery')->willReturn($fileResult);

        $connectionPool = $this->createMock(ConnectionPool::class);
        $connectionPool
            ->method('getQueryBuilderForTable')
            ->willReturnOnConsecutiveCalls($contentQb, $fileQb);

        return new AccessibilityCheckService($connectionPool);
    }

    #[Test]
    public function checkPageWithNoContentRowsPassesEmptyHtmlToChecks(): void
    {
        $service = $this->buildServiceWithContentRows([]);

        $check = $this->createMock(CheckInterface::class);
        $check->method('getIdentifier')->willReturn('test');
        $check->expects(self::once())->method('check')->with('', 1)->willReturn([]);

        $service->addCheck($check);
        $service->checkPage(1);
    }

    #[Test]
    public function checkPageBuildsHtmlFromContentElements(): void
    {
        $service = $this->buildServiceWithContentRows([
            ['header' => 'My Title', 'subheader' => 'Sub', 'bodytext' => '<p>Body</p>'],
        ]);

        $check = $this->createMock(CheckInterface::class);
        $check->method('getIdentifier')->willReturn('test');
        $check->expects(self::once())
            ->method('check')
            ->with(
                self::stringContains('My Title'),
                1
            )
            ->willReturn([]);

        $service->addCheck($check);
        $service->checkPage(1);
    }

    #[Test]
    public function checkPageReturnsAggregatedResultsFromAllChecks(): void
    {
        $service = $this->buildServiceWithContentRows([]);

        $result1 = CheckResult::error('check_a', 'Error A', '', 1);
        $result2 = CheckResult::warning('check_b', 'Warning B', '', 1);

        $checkA = $this->createMock(CheckInterface::class);
        $checkA->method('getIdentifier')->willReturn('check_a');
        $checkA->method('check')->willReturn([$result1]);

        $checkB = $this->createMock(CheckInterface::class);
        $checkB->method('getIdentifier')->willReturn('check_b');
        $checkB->method('check')->willReturn([$result2]);

        $service->addCheck($checkA);
        $service->addCheck($checkB);

        $results = $service->checkPage(1);

        self::assertCount(2, $results);
    }

    #[Test]
    public function checkPagesExcludesPagesWithNoIssues(): void
    {
        $connectionPool = $this->createMock(ConnectionPool::class);

        $emptyResult = $this->createMock(Result::class);
        $emptyResult->method('fetchAllAssociative')->willReturn([]);

        $exprBuilder = $this->createMock(ExpressionBuilder::class);
        $exprBuilder->method('eq')->willReturnArgument(0);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('join')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('expr')->willReturn($exprBuilder);
        $qb->method('createNamedParameter')->willReturnArgument(0);
        $qb->method('executeQuery')->willReturn($emptyResult);

        $connectionPool->method('getQueryBuilderForTable')->willReturn($qb);

        $service = new AccessibilityCheckService($connectionPool);

        $check = $this->createMock(CheckInterface::class);
        $check->method('getIdentifier')->willReturn('test');
        $check->method('check')->willReturn([]);

        $service->addCheck($check);

        $allResults = $service->checkPages([1, 2, 3]);

        self::assertSame([], $allResults);
    }

    #[Test]
    public function getRegisteredCheckIdentifiersReturnsAllAdded(): void
    {
        $connectionPool = $this->createMock(ConnectionPool::class);
        $service = new AccessibilityCheckService($connectionPool);

        $checkA = $this->createMock(CheckInterface::class);
        $checkA->method('getIdentifier')->willReturn('alpha');

        $checkB = $this->createMock(CheckInterface::class);
        $checkB->method('getIdentifier')->willReturn('beta');

        $service->addCheck($checkA);
        $service->addCheck($checkB);

        self::assertSame(['alpha', 'beta'], $service->getRegisteredCheckIdentifiers());
    }
}
