<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Check;

final class LinkTextCheck implements CheckInterface
{
    private const NON_DESCRIPTIVE = [
        'click here', 'here', 'read more', 'more', 'link', 'this link',
        'learn more', 'continue', 'click', 'go', 'more info', 'info',
        'details', 'more details', 'weiterlesen', 'mehr', 'hier', 'klicken',
        'докладніше', 'детальніше', 'اقرأ المزيد',
    ];

    public function getIdentifier(): string
    {
        return 'link_text';
    }

    public function check(string $html, int $pageUid): array
    {
        if (trim($html) === '') {
            return [];
        }

        $results = [];
        $doc = new \DOMDocument();
        @$doc->loadHTML('<meta charset="UTF-8">' . $html, \LIBXML_NOERROR);

        foreach ($doc->getElementsByTagName('a') as $anchor) {
            $href = $anchor->getAttribute('href');
            if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) {
                continue;
            }

            $hasAriaLabel = $anchor->hasAttribute('aria-label') && trim($anchor->getAttribute('aria-label')) !== '';
            $hasAriaLabelledBy = $anchor->hasAttribute('aria-labelledby');
            $hasTitle = $anchor->hasAttribute('title') && trim($anchor->getAttribute('title')) !== '';

            if ($hasAriaLabel || $hasAriaLabelledBy || $hasTitle) {
                continue;
            }

            $text = trim($anchor->textContent);

            if ($text === '') {
                $hasImg = $anchor->getElementsByTagName('img')->length > 0;
                if (!$hasImg) {
                    $results[] = CheckResult::error(
                        $this->getIdentifier(),
                        'Link has no discernible text and no image with alt text.',
                        sprintf('<a href="%s">', htmlspecialchars(substr($href, 0, 80))),
                        $pageUid,
                    );
                }
                continue;
            }

            if (in_array(strtolower($text), self::NON_DESCRIPTIVE, true)) {
                $results[] = CheckResult::warning(
                    $this->getIdentifier(),
                    sprintf('Non-descriptive link text: "%s".', htmlspecialchars($text)),
                    sprintf('<a href="%s">%s</a>', htmlspecialchars(substr($href, 0, 80)), htmlspecialchars($text)),
                    $pageUid,
                );
            }
        }

        return $results;
    }
}
