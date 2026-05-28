<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Check;

/**
 * Audits structural ARIA landmarks in content element HTML.
 *
 * Landmarks define major regions of a page (banner, navigation, main,
 * complementary, contentinfo, search, form) that assistive technology
 * users rely on for orientation and quick navigation.
 *
 * This check scans for both explicit role attributes and HTML5 semantic
 * elements that imply landmark roles. It flags duplicated unique landmarks
 * (main, banner, contentinfo) and duplicate non-unique landmarks without
 * distinguishing labels, then reports all detected landmarks for awareness.
 *
 * Missing-landmark warnings are intentionally omitted because this check
 * operates on content element HTML rather than the full page layout —
 * page-level landmarks (banner, main, contentinfo) live in the page template.
 */
final class LandmarksCheck implements CheckInterface
{
    private const array IMPLICIT_LANDMARKS = [
        'nav' => 'navigation',
        'main' => 'main',
        'aside' => 'complementary',
        'form' => 'form',
        'search' => 'search',
    ];

    private const array UNIQUE_LANDMARKS = ['main', 'banner', 'contentinfo'];

    public function getIdentifier(): string
    {
        return 'landmarks';
    }

    public function check(string $html, int $pageUid): array
    {
        if (trim($html) === '') {
            return [];
        }

        $doc = new \DOMDocument();
        @$doc->loadHTML('<meta charset="UTF-8">' . $html, \LIBXML_NOERROR);
        $xpath = new \DOMXPath($doc);

        $landmarks = $this->collectLandmarks($xpath);

        if ($landmarks === []) {
            return [];
        }

        $results = [];

        $results = $this->flagDuplicateUniqueLandmarks($landmarks, $pageUid, $results);

        $results = $this->flagUnlabelledDuplicateLandmarks($landmarks, $pageUid, $results);

        foreach ($landmarks as $lm) {
            $labelInfo = $lm['label'] !== ''
                ? sprintf(' labelled "%s"', htmlspecialchars(substr($lm['label'], 0, 80)))
                : '';
            $results[] = CheckResult::info(
                $this->getIdentifier(),
                sprintf('Found %s landmark%s.', $lm['role'], $labelInfo),
                $lm['tag'],
                $pageUid,
            );
        }

        return $results;
    }

    /**
     * @return array<int, array{role: string, label: string, tag: string}>
     */
    private function collectLandmarks(\DOMXPath $xpath): array
    {
        $landmarks = [];

        $roleQuery = $xpath->query(
            '//*[@role="banner" or @role="navigation" or @role="main"'
            . ' or @role="complementary" or @role="contentinfo"'
            . ' or @role="search" or @role="form"]',
        );
        if ($roleQuery !== false) {
            foreach ($roleQuery as $element) {
                if (!$element instanceof \DOMElement) {
                    continue;
                }
                $landmarks[] = $this->describeLandmark(
                    $element,
                    strtolower(trim($element->getAttribute('role'))),
                );
            }
        }

        foreach (self::IMPLICIT_LANDMARKS as $tagName => $role) {
            $elements = $xpath->query('//' . $tagName . '[not(@role)]');
            if ($elements === false) {
                continue;
            }
            foreach ($elements as $element) {
                if (!$element instanceof \DOMElement) {
                    continue;
                }
                $landmarks[] = $this->describeLandmark($element, $role);
            }
        }

        return $landmarks;
    }

    /**
     * @return array{role: string, label: string, tag: string}
     */
    private function describeLandmark(\DOMElement $element, string $role): array
    {
        $tag = '<' . $element->nodeName;
        if ($element->hasAttribute('role')) {
            $tag .= ' role="' . htmlspecialchars($element->getAttribute('role')) . '"';
        }
        if ($element->hasAttribute('class')) {
            $tag .= ' class="' . htmlspecialchars(substr($element->getAttribute('class'), 0, 60)) . '"';
        }
        if ($element->hasAttribute('aria-label')) {
            $tag .= ' aria-label="' . htmlspecialchars(substr($element->getAttribute('aria-label'), 0, 80)) . '"';
        }
        if ($element->hasAttribute('aria-labelledby')) {
            $tag .= ' aria-labelledby="' . htmlspecialchars($element->getAttribute('aria-labelledby')) . '"';
        }
        $tag .= '>';

        return [
            'role' => $role,
            'label' => $element->getAttribute('aria-label') ?: $element->getAttribute('aria-labelledby'),
            'tag' => $tag,
        ];
    }

    /**
     * @param array<int, array{role: string, label: string, tag: string}> $landmarks
     * @param CheckResult[] $results
     * @return CheckResult[]
     */
    private function flagDuplicateUniqueLandmarks(array $landmarks, int $pageUid, array $results): array
    {
        foreach (self::UNIQUE_LANDMARKS as $role) {
            $count = 0;
            $tags = [];
            foreach ($landmarks as $lm) {
                if ($lm['role'] === $role) {
                    $count++;
                    $tags[] = $lm['tag'];
                }
            }

            if ($count > 1) {
                foreach ($tags as $tag) {
                    $results[] = CheckResult::error(
                        $this->getIdentifier(),
                        sprintf(
                            'Multiple %s landmarks found (%d). This landmark typically appears once per page.',
                            $role,
                            $count,
                        ),
                        $tag,
                        $pageUid,
                    );
                }
            }
        }

        return $results;
    }

    /**
     * @param array<int, array{role: string, label: string, tag: string}> $landmarks
     * @param CheckResult[] $results
     * @return CheckResult[]
     */
    private function flagUnlabelledDuplicateLandmarks(array $landmarks, int $pageUid, array $results): array
    {
        $roleCounts = [];
        $roleTags = [];
        foreach ($landmarks as $lm) {
            $role = $lm['role'];
            $roleCounts[$role] = ($roleCounts[$role] ?? 0) + 1;
            $roleTags[$role][] = $lm;
        }

        foreach ($roleCounts as $role => $count) {
            if ($count < 2 || in_array($role, self::UNIQUE_LANDMARKS, true)) {
                continue;
            }

            $hasLabels = false;
            foreach ($roleTags[$role] as $lm) {
                if ($lm['label'] !== '') {
                    $hasLabels = true;
                    break;
                }
            }

            if (!$hasLabels) {
                foreach ($roleTags[$role] as $lm) {
                    $results[] = CheckResult::warning(
                        $this->getIdentifier(),
                        sprintf(
                            'Multiple %s landmarks without distinguishing labels. Add aria-label or aria-labelledby to differentiate them.',
                            $role,
                        ),
                        $lm['tag'],
                        $pageUid,
                    );
                }
            }
        }

        return $results;
    }
}
