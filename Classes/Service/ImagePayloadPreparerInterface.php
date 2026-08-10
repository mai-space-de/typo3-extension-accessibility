<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Service;

interface ImagePayloadPreparerInterface
{
    public function prepareBase64(int $fileUid): string;
}
