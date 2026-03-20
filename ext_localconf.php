<?php
defined('TYPO3') or die();

(static function (): void {
    // Register icons
    $iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
        \TYPO3\CMS\Core\Imaging\IconRegistry::class
    );
    $icons = [
        'module-maiaccessibility'          => 'Extension.svg',
        'module-maiaccessibility-alttext'  => 'module-alttext.svg',
        'module-maiaccessibility-heading'  => 'module-heading.svg',
        'module-maiaccessibility-aria'     => 'module-aria.svg',
        'module-maiaccessibility-linktext' => 'module-linktext.svg',
    ];
    foreach ($icons as $identifier => $file) {
        $iconRegistry->registerIcon(
            $identifier,
            \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
            ['source' => 'EXT:mai_accessibility/Resources/Public/Icons/' . $file]
        );
    }
})();
