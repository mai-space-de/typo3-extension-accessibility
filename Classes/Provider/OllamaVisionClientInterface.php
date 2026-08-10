<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Provider;

interface OllamaVisionClientInterface
{
    /**
     * @param list<string> $base64Images
     */
    public function complete(string $prompt, array $base64Images, ?string $systemPrompt = null): string;
}
