<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Check;

final class AriaAttributeCheck implements CheckInterface
{
    private const VALID_ROLES = [
        'alert', 'alertdialog', 'application', 'article', 'banner', 'button',
        'cell', 'checkbox', 'columnheader', 'combobox', 'complementary',
        'contentinfo', 'definition', 'dialog', 'directory', 'document',
        'feed', 'figure', 'form', 'grid', 'gridcell', 'group', 'heading',
        'img', 'link', 'list', 'listbox', 'listitem', 'log', 'main',
        'marquee', 'math', 'menu', 'menubar', 'menuitem', 'menuitemcheckbox',
        'menuitemradio', 'navigation', 'none', 'note', 'option', 'presentation',
        'progressbar', 'radio', 'radiogroup', 'region', 'row', 'rowgroup',
        'rowheader', 'scrollbar', 'search', 'searchbox', 'separator', 'slider',
        'spinbutton', 'status', 'switch', 'tab', 'table', 'tablist', 'tabpanel',
        'term', 'textbox', 'timer', 'toolbar', 'tooltip', 'tree', 'treegrid',
        'treeitem',
    ];

    public function getIdentifier(): string
    {
        return 'aria_attributes';
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

        $nodesWithRole = $xpath->query('//*[@role]');
        if ($nodesWithRole !== false) {
            foreach ($nodesWithRole as $node) {
                $role = $node->getAttribute('role');
                if (!in_array(strtolower(trim($role)), self::VALID_ROLES, true)) {
                    $results[] = CheckResult::error(
                        $this->getIdentifier(),
                        sprintf('Unknown ARIA role "%s".', htmlspecialchars($role)),
                        sprintf('<%s role="%s">', $node->nodeName, htmlspecialchars($role)),
                        $pageUid,
                    );
                }
            }
        }

        $nodesWithAriaHidden = $xpath->query('//*[@aria-hidden="true"][@tabindex]');
        if ($nodesWithAriaHidden !== false) {
            foreach ($nodesWithAriaHidden as $node) {
                $results[] = CheckResult::warning(
                    $this->getIdentifier(),
                    'Element is aria-hidden but has a tabindex — it may still receive focus.',
                    sprintf('<%s aria-hidden="true" tabindex="%s">', $node->nodeName, $node->getAttribute('tabindex')),
                    $pageUid,
                );
            }
        }

        $ariaLabelledBy = $xpath->query('//*[@aria-labelledby]');
        if ($ariaLabelledBy !== false) {
            foreach ($ariaLabelledBy as $node) {
                $refId = $node->getAttribute('aria-labelledby');
                $referenced = $doc->getElementById($refId);
                if ($referenced === null) {
                    $results[] = CheckResult::error(
                        $this->getIdentifier(),
                        sprintf('aria-labelledby references id="%s" which does not exist.', htmlspecialchars($refId)),
                        sprintf('<%s aria-labelledby="%s">', $node->nodeName, htmlspecialchars($refId)),
                        $pageUid,
                    );
                }
            }
        }

        return $results;
    }
}
