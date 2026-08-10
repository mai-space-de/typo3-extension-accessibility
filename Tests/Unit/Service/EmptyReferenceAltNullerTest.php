<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Tests\Unit\Service;

use Maispace\MaiAccessibility\Service\EmptyReferenceAltNuller;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

final class EmptyReferenceAltNullerTest extends TestCase
{
    #[Test]
    public function nullEmptyAlternativesUpdatesOnlyEmptyStrings(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('executeStatement')
            ->with(
                self::stringContains('SET alternative = NULL'),
                [15, ''],
                self::anything(),
            )
            ->willReturn(3);

        $pool = $this->createMock(ConnectionPool::class);
        $pool->method('getConnectionForTable')->with('sys_file_reference')->willReturn($connection);

        $nuller = new EmptyReferenceAltNuller($pool);
        self::assertSame(3, $nuller->nullEmptyAlternatives(15));
    }
}
