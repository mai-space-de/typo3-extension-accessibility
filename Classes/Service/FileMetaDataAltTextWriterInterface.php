<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Service;

interface FileMetaDataAltTextWriterInterface
{
    /**
     * @param array<int, string> $alternativesByLanguage
     */
    public function write(int $fileUid, array $alternativesByLanguage, bool $force = false): void;
}
