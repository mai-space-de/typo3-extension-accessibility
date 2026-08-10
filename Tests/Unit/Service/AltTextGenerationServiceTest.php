<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Tests\Unit\Service;

use Maispace\MaiAccessibility\Domain\Dto\AltTextCandidate;
use Maispace\MaiAccessibility\Provider\OllamaChatClientInterface;
use Maispace\MaiAccessibility\Provider\OllamaVisionClientInterface;
use Maispace\MaiAccessibility\Service\AltTextGenerationService;
use Maispace\MaiAccessibility\Service\EmptyReferenceAltNullerInterface;
use Maispace\MaiAccessibility\Service\FileMetaDataAltTextWriterInterface;
use Maispace\MaiAccessibility\Service\ImagePayloadPreparerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AltTextGenerationServiceTest extends TestCase
{
    #[Test]
    public function dryRunGeneratesGermanViaVisionAndTranslationsWithoutWriting(): void
    {
        $preparer = $this->createMock(ImagePayloadPreparerInterface::class);
        $preparer->expects(self::once())->method('prepareBase64')->with(42)->willReturn('base64img');

        $vision = $this->createMock(OllamaVisionClientInterface::class);
        $vision->expects(self::once())
            ->method('complete')
            ->with(self::isString(), ['base64img'], self::isString())
            ->willReturn('"Ein roter Apfel auf dem Tisch"');

        $chat = $this->createMock(OllamaChatClientInterface::class);
        $chat->expects(self::exactly(3))
            ->method('complete')
            ->willReturnOnConsecutiveCalls('A red apple on the table', 'Червоне яблуко', 'تفاحة حمراء');

        $writer = $this->createMock(FileMetaDataAltTextWriterInterface::class);
        $writer->expects(self::never())->method('write');

        $nuller = $this->createMock(EmptyReferenceAltNullerInterface::class);
        $nuller->expects(self::never())->method('nullEmptyAlternatives');

        $service = new AltTextGenerationService($preparer, $vision, $chat, $writer, $nuller);
        $candidate = new AltTextCandidate(
            fileUid: 42,
            identifier: '/user_upload/a.jpg',
            storage: 1,
            mimeType: 'image/jpeg',
            existingAlternatives: [0 => null, 1 => null, 2 => null, 3 => null],
            missingLanguageIds: [0, 1, 2, 3],
        );

        $result = $service->generateForCandidate($candidate, dryRun: true);

        self::assertFalse($result->skipped);
        self::assertTrue($result->dryRun);
        self::assertSame('Ein roter Apfel auf dem Tisch', $result->writtenAlternatives[0]);
        self::assertSame('A red apple on the table', $result->writtenAlternatives[1]);
        self::assertSame('Червоне яблуко', $result->writtenAlternatives[2]);
        self::assertSame('تفاحة حمراء', $result->writtenAlternatives[3]);
    }

    #[Test]
    public function reusesExistingGermanAndOnlyTranslatesMissingLanguages(): void
    {
        $preparer = $this->createMock(ImagePayloadPreparerInterface::class);
        $preparer->expects(self::never())->method('prepareBase64');

        $vision = $this->createMock(OllamaVisionClientInterface::class);
        $vision->expects(self::never())->method('complete');

        $chat = $this->createMock(OllamaChatClientInterface::class);
        $chat->expects(self::once())->method('complete')->willReturn('English alt');

        $writer = $this->createMock(FileMetaDataAltTextWriterInterface::class);
        $writer->expects(self::once())
            ->method('write')
            ->with(7, [1 => 'English alt'], false);

        $nuller = $this->createMock(EmptyReferenceAltNullerInterface::class);
        $nuller->expects(self::once())->method('nullEmptyAlternatives')->with(7)->willReturn(2);

        $service = new AltTextGenerationService($preparer, $vision, $chat, $writer, $nuller);
        $candidate = new AltTextCandidate(
            fileUid: 7,
            identifier: '/x.png',
            storage: 1,
            mimeType: 'image/png',
            existingAlternatives: [0 => 'Deutscher Alt', 1 => null, 2 => 'already', 3 => 'already'],
            missingLanguageIds: [1],
        );

        $result = $service->generateForCandidate($candidate, dryRun: false);

        self::assertFalse($result->skipped);
        self::assertSame(2, $result->nulledReferences);
        self::assertArrayNotHasKey(0, $result->writtenAlternatives);
        self::assertSame('English alt', $result->writtenAlternatives[1]);
    }

    #[Test]
    public function returnsSkippedWhenVisionFails(): void
    {
        $preparer = $this->createMock(ImagePayloadPreparerInterface::class);
        $preparer->method('prepareBase64')->willThrowException(new \RuntimeException('unreadable'));

        $service = new AltTextGenerationService(
            $preparer,
            $this->createMock(OllamaVisionClientInterface::class),
            $this->createMock(OllamaChatClientInterface::class),
            $this->createMock(FileMetaDataAltTextWriterInterface::class),
            $this->createMock(EmptyReferenceAltNullerInterface::class),
        );

        $candidate = new AltTextCandidate(
            fileUid: 1,
            identifier: '/broken.jpg',
            storage: 1,
            mimeType: 'image/jpeg',
            existingAlternatives: [0 => null],
            missingLanguageIds: [0],
        );

        $result = $service->generateForCandidate($candidate);
        self::assertTrue($result->skipped);
        self::assertSame('unreadable', $result->skipReason);
    }
}
