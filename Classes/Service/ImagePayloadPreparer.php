<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Service;

use Maispace\MaiAccessibility\Configuration\OllamaConfiguration;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * Loads a FAL image, optionally downscales it, and returns raw base64 for Ollama.
 */
final class ImagePayloadPreparer implements ImagePayloadPreparerInterface
{
    public function __construct(
        private readonly ResourceFactory $resourceFactory,
        private readonly OllamaConfiguration $configuration,
    ) {}

    /**
     * @throws \RuntimeException when the file cannot be read or is not an image
     */
    public function prepareBase64(int $fileUid): string
    {
        $file = $this->resourceFactory->getFileObject($fileUid);
        if (!$file->isImage()) {
            throw new \RuntimeException(sprintf('File %d is not an image.', $fileUid));
        }

        $localPath = $file->getForLocalProcessing(false);
        if (!is_readable($localPath)) {
            throw new \RuntimeException(sprintf('File %d is not readable at %s.', $fileUid, $localPath));
        }

        $binary = $this->resizeToJpegIfPossible($localPath, $this->configuration->maxImageEdge);
        if ($binary === null) {
            $binary = (string) file_get_contents($localPath);
        }

        if ($binary === '') {
            throw new \RuntimeException(sprintf('File %d is empty.', $fileUid));
        }

        return base64_encode($binary);
    }

    private function resizeToJpegIfPossible(string $localPath, int $maxEdge): ?string
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
            return null;
        }

        $raw = file_get_contents($localPath);
        if ($raw === false || $raw === '') {
            return null;
        }

        $source = @imagecreatefromstring($raw);
        if ($source === false) {
            return null;
        }

        $width = (int) imagesx($source);
        $height = (int) imagesy($source);

        $scale = min(1.0, $maxEdge / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        if ($scale < 1.0) {
            $resized = imagecreatetruecolor($targetWidth, $targetHeight);
            if ($resized === false) {
                return null;
            }
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
            $source = $resized;
        }

        // imagedestroy() is deprecated in PHP 8.5 (no-op since 8.0) — do not call it.
        ob_start();
        try {
            imagejpeg($source, null, 85);
            $jpeg = ob_get_clean();
        } catch (\Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }

        return is_string($jpeg) && $jpeg !== '' ? $jpeg : null;
    }
}
