<?php

declare(strict_types=1);

namespace MaiSpace\Accessability\Controller;

use MaiSpace\Accessability\Service\AccessabilityAnalysisService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;

class AltTextController
{
    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly AccessabilityAnalysisService $analysisService,
    ) {}

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $issues = $this->analysisService->findMissingAltTexts();
        $moduleTemplate->assign('issues', $issues);
        $moduleTemplate->assign('issueCount', count($issues));
        $moduleTemplate->assign('moduleName', 'Alt-Text');
        $moduleTemplate->assign('moduleDescription', 'This module lists all content elements that contain images without proper alternative text. Alternative text is required for screen reader users and is critical for WCAG 2.1 Success Criterion 1.1.1.');
        return $moduleTemplate->renderResponse('AltText/Index');
    }
}
