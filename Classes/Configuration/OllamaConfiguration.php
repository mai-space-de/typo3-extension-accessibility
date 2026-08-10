<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Configuration;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * Typed view of mai_accessibility Extension Configuration for Ollama.
 */
final readonly class OllamaConfiguration
{
    public string $baseUrl;

    public string $chatModel;

    public int $timeout;

    public int $maxImageEdge;

    public float $temperature;

    public function __construct(ExtensionConfiguration $extensionConfiguration)
    {
        try {
            $settings = $extensionConfiguration->get('mai_accessibility');
        } catch (\Throwable) {
            $settings = [];
        }

        $ollama = is_array($settings['ollama'] ?? null) ? $settings['ollama'] : [];

        $this->baseUrl = rtrim((string) ($ollama['baseUrl'] ?? 'http://localhost:11434'), '/');
        $this->chatModel = (string) ($ollama['chatModel'] ?? 'gemma4');
        $this->timeout = max(1, (int) ($ollama['timeout'] ?? 120));
        $this->maxImageEdge = max(64, (int) ($ollama['maxImageEdge'] ?? 1024));
        $this->temperature = max(0.0, min(1.0, (float) ($ollama['temperature'] ?? 0.2)));
    }
}
