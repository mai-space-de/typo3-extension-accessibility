<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Service;

use Doctrine\DBAL\ParameterType;
use Maispace\MaiAccessibility\Domain\Dto\AltTextCandidate;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\FileType;

/**
 * Finds image files whose sys_file_metadata.alternative is missing for one or more languages.
 */
final class MissingAltTextCandidateFinder implements MissingAltTextCandidateFinderInterface
{
    /** @var list<int> */
    private const DEFAULT_LANGUAGE_IDS = [0, 1, 2, 3];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * @param list<int>|null $languageIds Target language UIDs (default: site de/en/uk/ar = 0–3)
     * @return list<AltTextCandidate>
     */
    public function find(
        bool $force = false,
        ?int $limit = null,
        ?int $fileUid = null,
        ?int $storage = null,
        ?array $languageIds = null,
    ): array {
        $languageIds = $languageIds ?? self::DEFAULT_LANGUAGE_IDS;
        $languageIds = array_values(array_unique(array_map(static fn(int $id): int => $id, $languageIds)));
        if ($languageIds === []) {
            return [];
        }

        $qb = $this->connectionPool->getQueryBuilderForTable('sys_file');
        $qb->getRestrictions()->removeAll();

        $qb
            ->select('f.uid', 'f.identifier', 'f.storage', 'f.mime_type')
            ->from('sys_file', 'f')
            ->where(
                $qb->expr()->eq('f.type', $qb->createNamedParameter(FileType::IMAGE->value, ParameterType::INTEGER)),
                $qb->expr()->eq('f.missing', $qb->createNamedParameter(0, ParameterType::INTEGER)),
            )
            ->orderBy('f.uid', 'ASC');

        if ($fileUid !== null) {
            $qb->andWhere($qb->expr()->eq('f.uid', $qb->createNamedParameter($fileUid, ParameterType::INTEGER)));
        }
        if ($storage !== null) {
            $qb->andWhere($qb->expr()->eq('f.storage', $qb->createNamedParameter($storage, ParameterType::INTEGER)));
        }
        if ($limit !== null && $limit > 0) {
            $qb->setMaxResults($limit * 5);
        }

        $rows = $qb->executeQuery()->fetchAllAssociative();
        $candidates = [];

        foreach ($rows as $row) {
            $uid = (int) $row['uid'];
            $existing = $this->fetchAlternativesByLanguage($uid, $languageIds);
            $missing = [];
            foreach ($languageIds as $languageId) {
                $value = $existing[$languageId] ?? null;
                if ($force || $value === null || trim($value) === '') {
                    $missing[] = $languageId;
                }
            }

            if ($missing === []) {
                continue;
            }

            $candidates[] = new AltTextCandidate(
                fileUid: $uid,
                identifier: (string) $row['identifier'],
                storage: (int) $row['storage'],
                mimeType: (string) $row['mime_type'],
                existingAlternatives: $existing,
                missingLanguageIds: $missing,
            );

            if ($limit !== null && $limit > 0 && count($candidates) >= $limit) {
                break;
            }
        }

        return $candidates;
    }

    /**
     * @param list<int> $languageIds
     * @return array<int, string|null>
     */
    private function fetchAlternativesByLanguage(int $fileUid, array $languageIds): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('sys_file_metadata');
        $qb->getRestrictions()->removeAll();

        $rows = $qb
            ->select('sys_language_uid', 'alternative')
            ->from('sys_file_metadata')
            ->where(
                $qb->expr()->eq('file', $qb->createNamedParameter($fileUid, ParameterType::INTEGER)),
                $qb->expr()->in(
                    'sys_language_uid',
                    $qb->createNamedParameter($languageIds, \TYPO3\CMS\Core\Database\Connection::PARAM_INT_ARRAY),
                ),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $map = [];
        foreach ($languageIds as $languageId) {
            $map[$languageId] = null;
        }
        foreach ($rows as $row) {
            $languageId = (int) $row['sys_language_uid'];
            $map[$languageId] = $row['alternative'] !== null ? (string) $row['alternative'] : null;
        }

        return $map;
    }
}
