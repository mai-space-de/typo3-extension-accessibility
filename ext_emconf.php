<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'Accessibility Checks',
    'description' => 'Backend modules for editorial accessibility analysis: Alt-Text, Heading Structure, ARIA Attributes, and Link Text checks.',
    'category' => 'module',
    'author' => 'mai.space',
    'author_email' => '',
    'state' => 'beta',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-13.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
