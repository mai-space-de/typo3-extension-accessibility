<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Provider;

use Maispace\MaiAccessibility\Configuration\OllamaConfiguration;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * Non-streaming Ollama /api/chat client with base64 images for vision models.
 */
final class OllamaVisionClient implements OllamaVisionClientInterface
{
    public function __construct(
        private readonly RequestFactory $requestFactory,
        private readonly OllamaConfiguration $configuration,
    ) {}

    /**
     * @param list<string> $base64Images Raw base64 (no data-URI prefix)
     */
    public function complete(string $prompt, array $base64Images, ?string $systemPrompt = null): string
    {
        if ($base64Images === []) {
            throw new \InvalidArgumentException('At least one image is required for vision completion.', 1754740001);
        }

        $messages = [];
        if ($systemPrompt !== null && $systemPrompt !== '') {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = [
            'role' => 'user',
            'content' => $prompt,
            'images' => $base64Images,
        ];

        $response = $this->requestFactory->request(
            $this->configuration->baseUrl . '/api/chat',
            'POST',
            [
                'headers' => ['Content-Type' => 'application/json'],
                'timeout' => $this->configuration->timeout,
                'body' => json_encode([
                    'model' => $this->configuration->chatModel,
                    'stream' => false,
                    'options' => ['temperature' => $this->configuration->temperature],
                    'messages' => $messages,
                ], JSON_THROW_ON_ERROR),
            ],
        );

        return $this->extractContent($response);
    }

    private function extractContent(ResponseInterface $response): string
    {
        $statusCode = $response->getStatusCode();
        if ($statusCode >= 300) {
            throw new \RuntimeException(sprintf('Ollama vision API error (HTTP %d)', $statusCode));
        }

        $payload = json_decode((string) $response->getBody(), true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Ollama vision API returned invalid JSON.');
        }

        $content = $payload['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw new \RuntimeException('Ollama vision API returned an empty message.');
        }

        return trim($content);
    }
}
