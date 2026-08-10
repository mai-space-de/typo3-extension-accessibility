<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Tests\Unit\Provider;

use Maispace\MaiAccessibility\Configuration\OllamaConfiguration;
use Maispace\MaiAccessibility\Provider\OllamaChatClient;
use Maispace\MaiAccessibility\Provider\OllamaVisionClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\RequestFactory;

final class OllamaClientTest extends TestCase
{
    private function configuration(): OllamaConfiguration
    {
        $extConf = $this->createMock(ExtensionConfiguration::class);
        $extConf->method('get')->with('mai_accessibility')->willReturn([
            'ollama' => [
                'baseUrl' => 'http://ollama.test',
                'chatModel' => 'gemma4',
                'timeout' => 30,
                'maxImageEdge' => 512,
                'temperature' => 0.1,
            ],
        ]);

        return new OllamaConfiguration($extConf);
    }

    private function jsonResponse(array $payload, int $status = 200): ResponseInterface
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn(json_encode($payload, JSON_THROW_ON_ERROR));

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('getBody')->willReturn($stream);

        return $response;
    }

    #[Test]
    public function chatClientSendsNonStreamingPayload(): void
    {
        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->expects(self::once())
            ->method('request')
            ->with(
                'http://ollama.test/api/chat',
                'POST',
                self::callback(static function (array $options): bool {
                    $body = json_decode((string) $options['body'], true);
                    self::assertFalse($body['stream']);
                    self::assertSame('gemma4', $body['model']);
                    self::assertSame('user', $body['messages'][0]['role']);
                    self::assertSame('Hello', $body['messages'][0]['content']);
                    self::assertArrayNotHasKey('images', $body['messages'][0]);
                    return true;
                }),
            )
            ->willReturn($this->jsonResponse(['message' => ['content' => '  World  ']]));

        $client = new OllamaChatClient($requestFactory, $this->configuration());
        self::assertSame('World', $client->complete('Hello'));
    }

    #[Test]
    public function visionClientIncludesImagesAndRejectsEmptyList(): void
    {
        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->expects(self::once())
            ->method('request')
            ->with(
                'http://ollama.test/api/chat',
                'POST',
                self::callback(static function (array $options): bool {
                    $body = json_decode((string) $options['body'], true);
                    self::assertFalse($body['stream']);
                    self::assertSame(['abc123'], $body['messages'][1]['images']);
                    self::assertSame('system', $body['messages'][0]['role']);
                    return true;
                }),
            )
            ->willReturn($this->jsonResponse(['message' => ['content' => 'Ein Hund']]));

        $client = new OllamaVisionClient($requestFactory, $this->configuration());
        self::assertSame('Ein Hund', $client->complete('Beschreibe', ['abc123'], 'sys'));

        $this->expectException(\InvalidArgumentException::class);
        $client->complete('Beschreibe', []);
    }

    #[Test]
    public function chatClientThrowsOnHttpError(): void
    {
        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->method('request')->willReturn($this->jsonResponse(['error' => 'nope'], 500));

        $client = new OllamaChatClient($requestFactory, $this->configuration());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HTTP 500');
        $client->complete('Hello');
    }
}
