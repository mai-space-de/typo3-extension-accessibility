<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Service;

use Maispace\MaiAccessibility\Domain\Dto\AltTextCandidate;

interface MissingAltTextCandidateFinderInterface
{
    /**
     * @param list<int>|null $languageIds
     * @return list<AltTextCandidate>
     */
    public function find(
        bool $force = false,
        ?int $limit = null,
        ?int $fileUid = null,
        ?int $storage = null,
        ?array $languageIds = null,
    ): array;
}
