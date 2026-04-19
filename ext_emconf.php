<?php
$EM_CONF[$_EXTKEY] = [
    "title" => "Mai Accessibility",
    "description" =>
        "Backend module for editorial accessibility analysis. Checks content elements for common accessibility issues before publication. Includes TYPO3 link validation to surface broken links alongside accessibility warnings.",
    "category" => "module",
    "author" => "Maispace",
    "author_email" => "",
    "state" => "beta",
    "version" => "1.0.0",
    "constraints" => [
        "depends" => [
            "typo3" => "13.4.0-14.99.99",
        ],
        "conflicts" => [],
        "suggests" => [],
    ],
];
