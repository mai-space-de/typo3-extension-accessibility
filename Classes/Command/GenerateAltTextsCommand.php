<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Command;

use Maispace\MaiAccessibility\Service\AltTextGenerationServiceInterface;
use Maispace\MaiAccessibility\Service\MissingAltTextCandidateFinderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'mai-accessibility:generate-alt-texts',
    description: 'Generate multilingual image alt texts via Ollama and store them on sys_file_metadata.',
)]
final class GenerateAltTextsCommand extends Command
{
    public function __construct(
        private readonly MissingAltTextCandidateFinderInterface $candidateFinder,
        private readonly AltTextGenerationServiceInterface $generationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Generate and print results without writing to the database.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite existing non-empty metadata alternatives.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of files to process.')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Process a single sys_file UID.')
            ->addOption('storage', null, InputOption::VALUE_REQUIRED, 'Restrict to a single FAL storage UID.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $force = (bool) $input->getOption('force');
        $limit = $this->optionalInt($input->getOption('limit'));
        $fileUid = $this->optionalInt($input->getOption('file'));
        $storage = $this->optionalInt($input->getOption('storage'));

        $candidates = $this->candidateFinder->find(
            force: $force,
            limit: $limit,
            fileUid: $fileUid,
            storage: $storage,
        );

        if ($candidates === []) {
            $io->success('No image files need alt-text generation.');
            return Command::SUCCESS;
        }

        $io->title(sprintf('Generating alt texts for %d file(s)%s', count($candidates), $dryRun ? ' (dry-run)' : ''));

        $written = 0;
        $skipped = 0;
        $nulledTotal = 0;

        foreach ($candidates as $candidate) {
            $result = $this->generationService->generateForCandidate($candidate, $dryRun, $force);

            if ($result->skipped) {
                ++$skipped;
                $io->warning(sprintf('[%d] %s — skipped: %s', $result->fileUid, $result->identifier, $result->skipReason));
                continue;
            }

            ++$written;
            $nulledTotal += $result->nulledReferences;
            $langs = [];
            foreach ($result->writtenAlternatives as $languageId => $text) {
                $langs[] = sprintf('L%d: %s', $languageId, $text);
            }
            $io->writeln(sprintf(
                '[%d] %s — %s%s',
                $result->fileUid,
                $result->identifier,
                implode(' | ', $langs),
                $result->nulledReferences > 0 ? sprintf(' (nulled %d refs)', $result->nulledReferences) : '',
            ));
        }

        $io->success(sprintf(
            'Done. processed=%d written=%d skipped=%d nulled_refs=%d%s',
            count($candidates),
            $written,
            $skipped,
            $nulledTotal,
            $dryRun ? ' [dry-run]' : '',
        ));

        return Command::SUCCESS;
    }

    private function optionalInt(mixed $value): ?int
    {
        if ($value === null || $value === false || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
