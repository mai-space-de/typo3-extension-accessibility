<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Service;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * Writes alternative texts onto sys_file_metadata (default language + overlays).
 * Never writes to sys_file_reference.
 */
final class FileMetaDataAltTextWriter implements FileMetaDataAltTextWriterInterface
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly MetaDataRepository $metaDataRepository,
        private readonly ResourceFactory $resourceFactory,
    ) {}

    /**
     * @param array<int, string> $alternativesByLanguage languageId => alternative text
     */
    public function write(int $fileUid, array $alternativesByLanguage, bool $force = false): void
    {
        if ($alternativesByLanguage === []) {
            return;
        }

        // Ensure default-language metadata row exists (Core repository).
        $defaultMeta = $this->metaDataRepository->findByFileUid($fileUid);
        if ($defaultMeta === []) {
            $file = $this->resourceFactory->getFileObject($fileUid);
            $defaultMeta = $this->metaDataRepository->createMetaDataRecord($file->getUid());
        }

        $defaultMetaUid = (int) ($defaultMeta['uid'] ?? 0);
        if ($defaultMetaUid <= 0) {
            throw new \RuntimeException(sprintf('Could not resolve default metadata for file %d.', $fileUid));
        }

        if (isset($alternativesByLanguage[0])) {
            $this->writeDefaultLanguage($fileUid, $defaultMeta, $alternativesByLanguage[0], $force);
        }

        foreach ($alternativesByLanguage as $languageId => $alternative) {
            $languageId = (int) $languageId;
            if ($languageId <= 0) {
                continue;
            }
            $this->writeOverlay($fileUid, $defaultMetaUid, $languageId, $alternative, $force);
        }
    }

    /**
     * @param array<string, mixed> $defaultMeta
     */
    private function writeDefaultLanguage(int $fileUid, array $defaultMeta, string $alternative, bool $force): void
    {
        $existing = isset($defaultMeta['alternative']) ? trim((string) $defaultMeta['alternative']) : '';
        if (!$force && $existing !== '') {
            return;
        }

        $this->metaDataRepository->update($fileUid, ['alternative' => $alternative], $defaultMeta);
    }

    private function writeOverlay(
        int $fileUid,
        int $defaultMetaUid,
        int $languageId,
        string $alternative,
        bool $force,
    ): void {
        $connection = $this->connectionPool->getConnectionForTable('sys_file_metadata');
        $existing = $connection->select(
            ['uid', 'alternative'],
            'sys_file_metadata',
            [
                'file' => $fileUid,
                'sys_language_uid' => $languageId,
                'l10n_parent' => $defaultMetaUid,
            ],
        )->fetchAssociative();

        if ($existing === false) {
            $connection->insert('sys_file_metadata', [
                'file' => $fileUid,
                'pid' => 0,
                'crdate' => $GLOBALS['EXEC_TIME'] ?? time(),
                'tstamp' => $GLOBALS['EXEC_TIME'] ?? time(),
                'sys_language_uid' => $languageId,
                'l10n_parent' => $defaultMetaUid,
                'l10n_diffsource' => '',
                'alternative' => $alternative,
            ], [
                'l10n_diffsource' => Connection::PARAM_LOB,
            ]);
            return;
        }

        $existingAlt = trim((string) ($existing['alternative'] ?? ''));
        if (!$force && $existingAlt !== '') {
            return;
        }

        $connection->update(
            'sys_file_metadata',
            [
                'alternative' => $alternative,
                'tstamp' => $GLOBALS['EXEC_TIME'] ?? time(),
            ],
            ['uid' => (int) $existing['uid']],
        );
    }
}
