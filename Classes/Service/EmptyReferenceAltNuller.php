<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Service;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Sets empty sys_file_reference.alternative to NULL so FAL falls back to metadata.
 */
final class EmptyReferenceAltNuller implements EmptyReferenceAltNullerInterface
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * @return int Number of references updated
     */
    public function nullEmptyAlternatives(int $fileUid): int
    {
        $connection = $this->connectionPool->getConnectionForTable('sys_file_reference');

        return $connection->executeStatement(
            'UPDATE sys_file_reference SET alternative = NULL WHERE uid_local = ? AND deleted = 0 AND alternative = ?',
            [$fileUid, ''],
            [ParameterType::INTEGER, ParameterType::STRING],
        );
    }
}
