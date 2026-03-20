<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Controller;

use Maispace\MaiAccessibility\Service\AccessabilityAnalysisService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;

class HeadingStructureController
{
    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly AccessabilityAnalysisService $analysisService,
    ) {}

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $issues = $this->analysisService->findHeadingStructureIssues();
        $moduleTemplate->assign('issues', $issues);
        $moduleTemplate->assign('issueCount', count($issues));
        $moduleTemplate->assign('moduleName', 'Heading Structure');
        $moduleTemplate->assign('moduleDescription', 'This module analyses the heading hierarchy across all pages. A proper heading structure is essential for screen reader navigation and WCAG 2.1 Success Criterion 1.3.1 and 2.4.6.');
        return $moduleTemplate->renderResponse('HeadingStructure/Index');
    }
}
