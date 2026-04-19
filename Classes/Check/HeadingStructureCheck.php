<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Check;

final class HeadingStructureCheck implements CheckInterface
{
    public function getIdentifier(): string
    {
        return 'heading_structure';
    }

    public function check(string $html, int $pageUid): array
    {
        if (trim($html) === '') {
            return [];
        }

        $results = [];
        $doc = new \DOMDocument();
        @$doc->loadHTML('<meta charset="UTF-8">' . $html, \LIBXML_NOERROR);
        $xpath = new \DOMXPath($doc);

        $headings = $xpath->query('//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6]');
        if ($headings === false || $headings->length === 0) {
            return $results;
        }

        $levels = [];
        foreach ($headings as $heading) {
            $levels[] = (int)substr($heading->nodeName, 1);
        }

        $previousLevel = $levels[0];
        foreach (array_slice($levels, 1) as $level) {
            if ($level > $previousLevel + 1) {
                $results[] = CheckResult::error(
                    $this->getIdentifier(),
                    sprintf('Heading level skipped: h%d followed by h%d.', $previousLevel, $level),
                    '',
                    $pageUid,
                );
            }
            $previousLevel = $level;
        }

        $h1Count = count(array_filter($levels, static fn(int $l): bool => $l === 1));
        if ($h1Count === 0) {
            $results[] = CheckResult::warning(
                $this->getIdentifier(),
                'No h1 heading found on this page.',
                '',
                $pageUid,
            );
        } elseif ($h1Count > 1) {
            $results[] = CheckResult::warning(
                $this->getIdentifier(),
                sprintf('Multiple h1 headings found (%d). There should be exactly one h1.', $h1Count),
                '',
                $pageUid,
            );
        }

        return $results;
    }
}
