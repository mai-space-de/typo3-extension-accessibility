<?php

declare(strict_types=1);

use Maispace\MaiAccessibility\Controller\AltTextController;
use Maispace\MaiAccessibility\Controller\AriaAttributesController;
use Maispace\MaiAccessibility\Controller\HeadingStructureController;
use Maispace\MaiAccessibility\Controller\LinkTextController;

return [
    'accessibility' => [
        'labels' => 'LLL:EXT:mai_accessibility/Resources/Private/Language/locallang_mod.xlf:module.accessibility',
        'iconIdentifier' => 'module-maiaccessibility',
        'position' => ['after' => 'web'],
    ],
    'accessibility_alttext' => [
        'parent' => 'accessibility',
        'access' => 'user',
        'iconIdentifier' => 'module-maiaccessibility-alttext',
        'labels' => 'LLL:EXT:mai_accessibility/Resources/Private/Language/locallang_mod.xlf:module.alttext',
        'routes' => [
            '_default' => [
                'target' => AltTextController::class . '::handleRequest',
            ],
        ],
    ],
    'accessibility_heading' => [
        'parent' => 'accessibility',
        'access' => 'user',
        'iconIdentifier' => 'module-maiaccessibility-heading',
        'labels' => 'LLL:EXT:mai_accessibility/Resources/Private/Language/locallang_mod.xlf:module.heading',
        'routes' => [
            '_default' => [
                'target' => HeadingStructureController::class . '::handleRequest',
            ],
        ],
    ],
    'accessibility_aria' => [
        'parent' => 'accessibility',
        'access' => 'user',
        'iconIdentifier' => 'module-maiaccessibility-aria',
        'labels' => 'LLL:EXT:mai_accessibility/Resources/Private/Language/locallang_mod.xlf:module.aria',
        'routes' => [
            '_default' => [
                'target' => AriaAttributesController::class . '::handleRequest',
            ],
        ],
    ],
    'accessibility_linktext' => [
        'parent' => 'accessibility',
        'access' => 'user',
        'iconIdentifier' => 'module-maiaccessibility-linktext',
        'labels' => 'LLL:EXT:mai_accessibility/Resources/Private/Language/locallang_mod.xlf:module.linktext',
        'routes' => [
            '_default' => [
                'target' => LinkTextController::class . '::handleRequest',
            ],
        ],
    ],
];
