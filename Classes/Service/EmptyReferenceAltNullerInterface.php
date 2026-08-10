<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Service;

interface EmptyReferenceAltNullerInterface
{
    public function nullEmptyAlternatives(int $fileUid): int;
}
