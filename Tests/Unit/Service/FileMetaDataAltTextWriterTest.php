<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Tests\Unit\Service;

use Maispace\MaiAccessibility\Service\FileMetaDataAltTextWriter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;

final class FileMetaDataAltTextWriterTest extends TestCase
{
    #[Test]
    public function writesDefaultLanguageViaRepositoryAndCreatesOverlay(): void
    {
        $metaRepo = $this->createMock(MetaDataRepository::class);
        $metaRepo->method('findByFileUid')->with(5)->willReturn([
            'uid' => 50,
            'file' => 5,
            'alternative' => '',
            'sys_language_uid' => 0,
        ]);
        $metaRepo->expects(self::once())
            ->method('update')
            ->with(5, ['alternative' => 'DE alt'], self::isArray());

        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $result->method('fetchAssociative')->willReturn(false);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('select')
            ->willReturn($result);
        $connection->expects(self::once())
            ->method('insert')
            ->with(
                'sys_file_metadata',
                self::callback(static function (array $row): bool {
                    return $row['file'] === 5
                        && $row['sys_language_uid'] === 1
                        && $row['l10n_parent'] === 50
                        && $row['alternative'] === 'EN alt';
                }),
                self::anything(),
            );

        $pool = $this->createMock(ConnectionPool::class);
        $pool->method('getConnectionForTable')->with('sys_file_metadata')->willReturn($connection);

        $writer = new FileMetaDataAltTextWriter(
            $pool,
            $metaRepo,
            $this->createMock(ResourceFactory::class),
        );
        $writer->write(5, [0 => 'DE alt', 1 => 'EN alt'], force: false);
    }

    #[Test]
    public function skipsExistingOverlayWithoutForce(): void
    {
        $metaRepo = $this->createMock(MetaDataRepository::class);
        $metaRepo->method('findByFileUid')->willReturn([
            'uid' => 50,
            'file' => 5,
            'alternative' => 'Existing DE',
            'sys_language_uid' => 0,
        ]);
        $metaRepo->expects(self::never())->method('update');

        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $result->method('fetchAssociative')->willReturn([
            'uid' => 99,
            'alternative' => 'Existing EN',
        ]);

        $connection = $this->createMock(Connection::class);
        $connection->method('select')->willReturn($result);
        $connection->expects(self::never())->method('insert');
        $connection->expects(self::never())->method('update');

        $pool = $this->createMock(ConnectionPool::class);
        $pool->method('getConnectionForTable')->willReturn($connection);

        $writer = new FileMetaDataAltTextWriter(
            $pool,
            $metaRepo,
            $this->createMock(ResourceFactory::class),
        );
        $writer->write(5, [0 => 'New DE', 1 => 'New EN'], force: false);
    }
}
