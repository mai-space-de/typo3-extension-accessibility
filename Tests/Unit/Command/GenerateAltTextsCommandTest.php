<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Tests\Unit\Command;

use Maispace\MaiAccessibility\Command\GenerateAltTextsCommand;
use Maispace\MaiAccessibility\Domain\Dto\AltTextCandidate;
use Maispace\MaiAccessibility\Domain\Dto\AltTextGenerationResult;
use Maispace\MaiAccessibility\Service\AltTextGenerationServiceInterface;
use Maispace\MaiAccessibility\Service\MissingAltTextCandidateFinderInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class GenerateAltTextsCommandTest extends TestCase
{
    #[Test]
    public function reportsSuccessWhenNothingToDo(): void
    {
        $finder = $this->createMock(MissingAltTextCandidateFinderInterface::class);
        $finder->method('find')->willReturn([]);

        $tester = new CommandTester(new GenerateAltTextsCommand(
            $finder,
            $this->createMock(AltTextGenerationServiceInterface::class),
        ));
        $exit = $tester->execute([]);

        self::assertSame(0, $exit);
        self::assertStringContainsString('No image files need alt-text generation', $tester->getDisplay());
    }

    #[Test]
    public function processesCandidatesAndPrintsSummary(): void
    {
        $candidate = new AltTextCandidate(
            fileUid: 9,
            identifier: '/a.jpg',
            storage: 1,
            mimeType: 'image/jpeg',
            existingAlternatives: [0 => null],
            missingLanguageIds: [0],
        );

        $finder = $this->createMock(MissingAltTextCandidateFinderInterface::class);
        $finder->method('find')->with(false, 5, null, null)->willReturn([$candidate]);

        $generation = $this->createMock(AltTextGenerationServiceInterface::class);
        $generation->method('generateForCandidate')->willReturn(new AltTextGenerationResult(
            fileUid: 9,
            identifier: '/a.jpg',
            skipped: false,
            skipReason: '',
            writtenAlternatives: [0 => 'Test alt'],
            nulledReferences: 1,
            dryRun: true,
        ));

        $tester = new CommandTester(new GenerateAltTextsCommand($finder, $generation));
        $exit = $tester->execute(['--dry-run' => true, '--limit' => '5']);

        self::assertSame(0, $exit);
        self::assertStringContainsString('Test alt', $tester->getDisplay());
        self::assertStringContainsString('written=1', $tester->getDisplay());
    }
}
