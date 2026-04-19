<?php

declare(strict_types=1);

use Maispace\MaiAccessibility\Controller\Backend\AccessibilityController;

return [
    'mai_accessibility' => [
        'parent' => 'tools',
        'access' => 'admin',
        'workspaces' => 'online',
        'path' => '/module/mai-accessibility',
        'iconIdentifier' => 'ext-maispace-mai_accessibility',
        'labels' => 'LLL:EXT:mai_accessibility/Resources/Private/Language/locallang.xlf',
        'extensionName' => 'MaiAccessibility',
        'controllerActions' => [
            AccessibilityController::class => [
                'index',
                'check',
                'exportCsv',
            ],
        ],
    ],
];
