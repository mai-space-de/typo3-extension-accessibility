<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Check;

final class AltTextCheck implements CheckInterface
{
    public function getIdentifier(): string
    {
        return 'alt_text';
    }

    public function check(string $html, int $pageUid): array
    {
        if (trim($html) === '') {
            return [];
        }

        $results = [];
        $doc = new \DOMDocument();
        @$doc->loadHTML('<meta charset="UTF-8">' . $html, \LIBXML_NOERROR);
        $images = $doc->getElementsByTagName('img');

        foreach ($images as $img) {
            $alt = $img->getAttribute('alt');
            $src = $img->getAttribute('src');
            $context = $src !== '' ? sprintf('<img src="%s">', htmlspecialchars(substr($src, 0, 80))) : '<img>';

            if (!$img->hasAttribute('alt')) {
                $results[] = CheckResult::error(
                    $this->getIdentifier(),
                    'Image is missing alt attribute.',
                    $context,
                    $pageUid,
                );
            } elseif (trim($alt) === '') {
                $results[] = CheckResult::warning(
                    $this->getIdentifier(),
                    'Image has empty alt attribute — ensure this is decorative.',
                    $context,
                    $pageUid,
                );
            }
        }

        return $results;
    }
}
