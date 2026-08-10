<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Domain\Dto;

/**
 * Result of generating/writing alt texts for one file.
 *
 * @phpstan-type WrittenAlts array<int, string>
 */
final readonly class AltTextGenerationResult
{
    /**
     * @param WrittenAlts $writtenAlternatives languageId => written text
     */
    public function __construct(
        public int $fileUid,
        public string $identifier,
        public bool $skipped,
        public string $skipReason,
        public array $writtenAlternatives,
        public int $nulledReferences,
        public bool $dryRun,
    ) {}

    public static function skipped(int $fileUid, string $identifier, string $reason): self
    {
        return new self($fileUid, $identifier, true, $reason, [], 0, false);
    }
}
