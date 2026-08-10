<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Service;

use Maispace\MaiAccessibility\Domain\Dto\AltTextCandidate;
use Maispace\MaiAccessibility\Domain\Dto\AltTextGenerationResult;

interface AltTextGenerationServiceInterface
{
    public function generateForCandidate(
        AltTextCandidate $candidate,
        bool $dryRun = false,
        bool $force = false,
    ): AltTextGenerationResult;
}
