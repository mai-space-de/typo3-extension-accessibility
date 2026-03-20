<?php

declare(strict_types=1);

use MaiSpace\Accessability\Controller\AltTextController;
use MaiSpace\Accessability\Controller\AriaAttributesController;
use MaiSpace\Accessability\Controller\HeadingStructureController;
use MaiSpace\Accessability\Controller\LinkTextController;

return [
    'accessability' => [
        'labels' => 'LLL:EXT:accessability/Resources/Private/Language/locallang_mod.xlf:module.accessability',
        'iconIdentifier' => 'module-accessability',
        'position' => ['after' => 'web'],
    ],
    'accessability_alttext' => [
        'parent' => 'accessability',
        'access' => 'user',
        'iconIdentifier' => 'module-accessability-alttext',
        'labels' => 'LLL:EXT:accessability/Resources/Private/Language/locallang_mod.xlf:module.alttext',
        'routes' => [
            '_default' => [
                'target' => AltTextController::class . '::handleRequest',
            ],
        ],
    ],
    'accessability_heading' => [
        'parent' => 'accessability',
        'access' => 'user',
        'iconIdentifier' => 'module-accessability-heading',
        'labels' => 'LLL:EXT:accessability/Resources/Private/Language/locallang_mod.xlf:module.heading',
        'routes' => [
            '_default' => [
                'target' => HeadingStructureController::class . '::handleRequest',
            ],
        ],
    ],
    'accessability_aria' => [
        'parent' => 'accessability',
        'access' => 'user',
        'iconIdentifier' => 'module-accessability-aria',
        'labels' => 'LLL:EXT:accessability/Resources/Private/Language/locallang_mod.xlf:module.aria',
        'routes' => [
            '_default' => [
                'target' => AriaAttributesController::class . '::handleRequest',
            ],
        ],
    ],
    'accessability_linktext' => [
        'parent' => 'accessability',
        'access' => 'user',
        'iconIdentifier' => 'module-accessability-linktext',
        'labels' => 'LLL:EXT:accessability/Resources/Private/Language/locallang_mod.xlf:module.linktext',
        'routes' => [
            '_default' => [
                'target' => LinkTextController::class . '::handleRequest',
            ],
        ],
    ],
];
