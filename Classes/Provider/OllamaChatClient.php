<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Provider;

use Maispace\MaiAccessibility\Configuration\OllamaConfiguration;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * Non-streaming Ollama /api/chat client for text-only prompts (translations).
 */
final class OllamaChatClient implements OllamaChatClientInterface
{
    public function __construct(
        private readonly RequestFactory $requestFactory,
        private readonly OllamaConfiguration $configuration,
    ) {}

    public function complete(string $prompt, ?string $systemPrompt = null): string
    {
        $messages = [];
        if ($systemPrompt !== null && $systemPrompt !== '') {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

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
            throw new \RuntimeException(sprintf('Ollama chat API error (HTTP %d)', $statusCode));
        }

        $payload = json_decode((string) $response->getBody(), true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Ollama chat API returned invalid JSON.');
        }

        $content = $payload['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw new \RuntimeException('Ollama chat API returned an empty message.');
        }

        return trim($content);
    }
}
