<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Check;

/**
 * Checks for keyboard focus-trap issues in interactive components:
 *  1. Modal dialogs (role="dialog" / role="alertdialog") without focusable content.
 *  2. aria-hidden="true" containers that still contain focusable elements.
 *  3. Tabpanel elements (role="tabpanel") without focusable content.
 */
final class FocusTrapCheck implements CheckInterface
{
    /**
     * XPath expression matching elements that are natively focusable
     * or have an explicit non-negative tabindex.
     */
    private const string FOCUSABLE_XPATH = '
        .//*
        [
            local-name() = "a" and @href
            or local-name() = "button" and not(@disabled)
            or local-name() = "input" and not(@disabled) and not(@type = "hidden")
            or local-name() = "select" and not(@disabled)
            or local-name() = "textarea" and not(@disabled)
            or local-name() = "audio" and @controls
            or local-name() = "video" and @controls
            or @tabindex != "-1"
        ]
    ';

    public function getIdentifier(): string
    {
        return 'focus_trap';
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

        array_push($results, ...$this->checkModalDialogs($xpath, $doc, $pageUid));
        array_push($results, ...$this->checkAriaHiddenWithFocusable($xpath, $pageUid));
        array_push($results, ...$this->checkTabpanels($xpath, $pageUid));

        return $results;
    }

    /**
     * @return CheckResult[]
     */
    private function checkModalDialogs(\DOMXPath $xpath, \DOMDocument $doc, int $pageUid): array
    {
        $results = [];

        $modalRoles = $xpath->query('//*[@role="dialog" or @role="alertdialog"]');
        if ($modalRoles === false || $modalRoles->length === 0) {
            return [];
        }

        foreach ($modalRoles as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $role = strtolower(trim($node->getAttribute('role')));

            $focusableNodes = $xpath->evaluate(self::FOCUSABLE_XPATH, $node);
            $hasFocusable = $focusableNodes !== false && $focusableNodes->length > 0;

            if (!$hasFocusable) {
                $context = sprintf(
                    '<%s role="%s"%s>',
                    $node->nodeName,
                    htmlspecialchars($role),
                    $node->hasAttribute('aria-label')
                        ? ' aria-label="' . htmlspecialchars($node->getAttribute('aria-label')) . '"'
                        : ($node->hasAttribute('aria-labelledby')
                            ? ' aria-labelledby="' . htmlspecialchars($node->getAttribute('aria-labelledby')) . '"'
                            : ''),
                );

                $results[] = CheckResult::error(
                    $this->getIdentifier(),
                    sprintf(
                        'Modal with role="%s" contains no focusable elements — keyboard users may be trapped.',
                        $role,
                    ),
                    $context,
                    $pageUid,
                );
            }
        }

        return $results;
    }

    /**
     * @return CheckResult[]
     */
    private function checkAriaHiddenWithFocusable(\DOMXPath $xpath, int $pageUid): array
    {
        $results = [];

        $ariaHidden = $xpath->query('//*[@aria-hidden="true"]');
        if ($ariaHidden === false || $ariaHidden->length === 0) {
            return [];
        }

        foreach ($ariaHidden as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $focusableNodes = $xpath->evaluate(self::FOCUSABLE_XPATH, $node);
            $hasFocusable = $focusableNodes !== false && $focusableNodes->length > 0;

            if ($hasFocusable) {
                $context = sprintf(
                    '<%s aria-hidden="true"%s>',
                    $node->nodeName,
                    $node->hasAttribute('id')
                        ? ' id="' . htmlspecialchars($node->getAttribute('id')) . '"'
                        : '',
                );

                $results[] = CheckResult::warning(
                    $this->getIdentifier(),
                    'Element is aria-hidden="true" but contains focusable elements — keyboard users may focus hidden content.',
                    $context,
                    $pageUid,
                );
            }
        }

        return $results;
    }

    /**
     * @return CheckResult[]
     */
    private function checkTabpanels(\DOMXPath $xpath, int $pageUid): array
    {
        $results = [];

        $tabpanels = $xpath->query('//*[@role="tabpanel"]');
        if ($tabpanels === false || $tabpanels->length === 0) {
            return [];
        }

        foreach ($tabpanels as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $focusableNodes = $xpath->evaluate(self::FOCUSABLE_XPATH, $node);
            $hasFocusable = $focusableNodes !== false && $focusableNodes->length > 0;

            if (!$hasFocusable) {
                $context = sprintf(
                    '<%s role="tabpanel"%s>',
                    $node->nodeName,
                    $node->hasAttribute('id')
                        ? ' id="' . htmlspecialchars($node->getAttribute('id')) . '"'
                        : '',
                );

                $results[] = CheckResult::warning(
                    $this->getIdentifier(),
                    'Tabpanel with role="tabpanel" contains no focusable elements — keyboard users cannot interact with tab content.',
                    $context,
                    $pageUid,
                );
            }
        }

        return $results;
    }
}
