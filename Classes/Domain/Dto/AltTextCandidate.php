<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Domain\Dto;

/**
 * A sys_file image that needs (some) alt-text metadata filled.
 *
 * @phpstan-type LanguageAltMap array<int, string|null>
 */
final readonly class AltTextCandidate
{
    /**
     * @param LanguageAltMap $existingAlternatives languageId => alternative (null/empty = missing)
     * @param list<int> $missingLanguageIds language UIDs that still need a value
     */
    public function __construct(
        public int $fileUid,
        public string $identifier,
        public int $storage,
        public string $mimeType,
        public array $existingAlternatives,
        public array $missingLanguageIds,
    ) {}

    public function needsDefaultLanguage(): bool
    {
        return in_array(0, $this->missingLanguageIds, true);
    }
}
