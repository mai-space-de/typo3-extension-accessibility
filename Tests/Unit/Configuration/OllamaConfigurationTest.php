<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Tests\Unit\Configuration;

use Maispace\MaiAccessibility\Configuration\OllamaConfiguration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

final class OllamaConfigurationTest extends TestCase
{
    #[Test]
    public function readsNestedOllamaSettings(): void
    {
        $extConf = $this->createMock(ExtensionConfiguration::class);
        $extConf->method('get')->willReturn([
            'ollama' => [
                'baseUrl' => 'http://host.docker.internal:11434/',
                'chatModel' => 'gemma4',
                'timeout' => '90',
                'maxImageEdge' => '800',
                'temperature' => '0.5',
            ],
        ]);

        $config = new OllamaConfiguration($extConf);

        self::assertSame('http://host.docker.internal:11434', $config->baseUrl);
        self::assertSame('gemma4', $config->chatModel);
        self::assertSame(90, $config->timeout);
        self::assertSame(800, $config->maxImageEdge);
        self::assertSame(0.5, $config->temperature);
    }

    #[Test]
    public function fallsBackToDefaultsWhenExtensionConfigMissing(): void
    {
        $extConf = $this->createMock(ExtensionConfiguration::class);
        $extConf->method('get')->willThrowException(new \RuntimeException('missing'));

        $config = new OllamaConfiguration($extConf);

        self::assertSame('http://localhost:11434', $config->baseUrl);
        self::assertSame('gemma4', $config->chatModel);
    }
}
