<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Provider;

interface OllamaChatClientInterface
{
    public function complete(string $prompt, ?string $systemPrompt = null): string;
}
