<?php

declare(strict_types=1);

namespace MaiSpace\Accessability\Controller;

use MaiSpace\Accessability\Service\AccessabilityAnalysisService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;

class AriaAttributesController
{
    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly AccessabilityAnalysisService $analysisService,
    ) {}

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $issues = $this->analysisService->findAriaIssues();
        $moduleTemplate->assign('issues', $issues);
        $moduleTemplate->assign('issueCount', count($issues));
        $moduleTemplate->assign('moduleName', 'ARIA Attributes & Roles');
        $moduleTemplate->assign('moduleDescription', 'This module scans RTE content for common ARIA attribute problems such as images missing alt attributes and anchors with role="button" that lack an href. Proper ARIA usage is required by WCAG 2.1 Success Criterion 4.1.2.');
        return $moduleTemplate->renderResponse('AriaAttributes/Index');
    }
}
