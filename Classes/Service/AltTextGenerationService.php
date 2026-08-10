<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Service;

use Maispace\MaiAccessibility\Domain\Dto\AltTextCandidate;
use Maispace\MaiAccessibility\Domain\Dto\AltTextGenerationResult;
use Maispace\MaiAccessibility\Provider\OllamaChatClientInterface;
use Maispace\MaiAccessibility\Provider\OllamaVisionClientInterface;

/**
 * Orchestrates vision (DE) + text-only translations, then persists metadata.
 */
final class AltTextGenerationService implements AltTextGenerationServiceInterface
{
    private const MAX_ALT_LENGTH = 125;

    /** @var array<int, string> */
    private const LANGUAGE_ISO = [
        0 => 'de',
        1 => 'en',
        2 => 'uk',
        3 => 'ar',
    ];

    public function __construct(
        private readonly ImagePayloadPreparerInterface $imagePayloadPreparer,
        private readonly OllamaVisionClientInterface $visionClient,
        private readonly OllamaChatClientInterface $chatClient,
        private readonly FileMetaDataAltTextWriterInterface $metaDataWriter,
        private readonly EmptyReferenceAltNullerInterface $referenceAltNuller,
    ) {}

    public function generateForCandidate(
        AltTextCandidate $candidate,
        bool $dryRun = false,
        bool $force = false,
    ): AltTextGenerationResult {
        try {
            $alternatives = $this->buildAlternatives($candidate);
        } catch (\Throwable $exception) {
            return AltTextGenerationResult::skipped(
                $candidate->fileUid,
                $candidate->identifier,
                $exception->getMessage(),
            );
        }

        if ($alternatives === []) {
            return AltTextGenerationResult::skipped(
                $candidate->fileUid,
                $candidate->identifier,
                'Nothing to generate.',
            );
        }

        if ($dryRun) {
            return new AltTextGenerationResult(
                fileUid: $candidate->fileUid,
                identifier: $candidate->identifier,
                skipped: false,
                skipReason: '',
                writtenAlternatives: $alternatives,
                nulledReferences: 0,
                dryRun: true,
            );
        }

        $this->metaDataWriter->write($candidate->fileUid, $alternatives, $force);
        $nulled = $this->referenceAltNuller->nullEmptyAlternatives($candidate->fileUid);

        return new AltTextGenerationResult(
            fileUid: $candidate->fileUid,
            identifier: $candidate->identifier,
            skipped: false,
            skipReason: '',
            writtenAlternatives: $alternatives,
            nulledReferences: $nulled,
            dryRun: false,
        );
    }

    /**
     * @return array<int, string>
     */
    private function buildAlternatives(AltTextCandidate $candidate): array
    {
        $missing = $candidate->missingLanguageIds;
        if ($missing === []) {
            return [];
        }

        $german = $this->resolveGermanAlt($candidate);
        $result = [];

        if (in_array(0, $missing, true)) {
            $result[0] = $german;
        }

        foreach ($missing as $languageId) {
            if ($languageId === 0) {
                continue;
            }
            $iso = self::LANGUAGE_ISO[$languageId] ?? null;
            if ($iso === null) {
                continue;
            }
            $result[$languageId] = $this->normalizeAlt($this->chatClient->complete(
                $this->buildTranslatePrompt($german, $iso),
                $this->translationSystemPrompt(),
            ));
        }

        return $result;
    }

    private function resolveGermanAlt(AltTextCandidate $candidate): string
    {
        $existingDe = trim((string) ($candidate->existingAlternatives[0] ?? ''));
        if ($existingDe !== '' && !$candidate->needsDefaultLanguage()) {
            return $this->normalizeAlt($existingDe);
        }

        // When DE is missing (or force listed it as missing), run vision.
        if ($candidate->needsDefaultLanguage() || $existingDe === '') {
            $base64 = $this->imagePayloadPreparer->prepareBase64($candidate->fileUid);
            return $this->normalizeAlt($this->visionClient->complete(
                $this->visionUserPrompt(),
                [$base64],
                $this->visionSystemPrompt(),
            ));
        }

        return $this->normalizeAlt($existingDe);
    }

    private function visionSystemPrompt(): string
    {
        return 'Du schreibst barrierefreie Bildbeschreibungen (Alt-Texte) auf Deutsch. '
            . 'Antworte nur mit dem Alt-Text, ohne Anführungszeichen und ohne Erklärung.';
    }

    private function visionUserPrompt(): string
    {
        return 'Beschreibe dieses Bild in einem kurzen, sachlichen deutschen Alt-Text '
            . '(höchstens ' . self::MAX_ALT_LENGTH . ' Zeichen). '
            . 'Keine Phrasen wie „Bild von“ oder „Foto zeigt“. '
            . 'Fokus auf den für Screenreader relevanten Inhalt.';
    }

    private function translationSystemPrompt(): string
    {
        return 'You translate image alt texts. Reply with only the translated alt text, '
            . 'no quotes and no explanation. Keep it short and factual.';
    }

    private function buildTranslatePrompt(string $germanAlt, string $targetIso): string
    {
        return sprintf(
            "Translate this German image alt text into %s (max %d characters):\n\n%s",
            $targetIso,
            self::MAX_ALT_LENGTH,
            $germanAlt,
        );
    }

    private function normalizeAlt(string $raw): string
    {
        $text = trim($raw);
        $text = trim($text, " \t\n\r\0\x0B\"'");
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        if (mb_strlen($text) > self::MAX_ALT_LENGTH) {
            $text = rtrim(mb_substr($text, 0, self::MAX_ALT_LENGTH - 1)) . '…';
        }

        if ($text === '') {
            throw new \RuntimeException('Generated alt text was empty after normalization.');
        }

        return $text;
    }
}
