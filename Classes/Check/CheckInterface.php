<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Check;

interface CheckInterface
{
    /**
     * Returns the unique identifier for this check.
     */
    public function getIdentifier(): string;

    /**
     * Runs the check against the given page content HTML.
     *
     * @return CheckResult[]
     */
    public function check(string $html, int $pageUid): array;
}
