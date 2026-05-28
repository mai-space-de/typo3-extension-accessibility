<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Service;

interface FrontendHtmlFetcherInterface
{
    public function fetchHtmlForPage(int $pageUid): string;
}
