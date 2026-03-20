<?php
defined('TYPO3') or die();

(static function (): void {
    // Register icons
    $iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
        \TYPO3\CMS\Core\Imaging\IconRegistry::class
    );
    $icons = [
        'module-accessability'          => 'Extension.svg',
        'module-accessability-alttext'  => 'module-alttext.svg',
        'module-accessability-heading'  => 'module-heading.svg',
        'module-accessability-aria'     => 'module-aria.svg',
        'module-accessability-linktext' => 'module-linktext.svg',
    ];
    foreach ($icons as $identifier => $file) {
        $iconRegistry->registerIcon(
            $identifier,
            \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
            ['source' => 'EXT:accessability/Resources/Public/Icons/' . $file]
        );
    }
})();
