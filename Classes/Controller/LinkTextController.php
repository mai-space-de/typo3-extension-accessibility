<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Controller;

use Maispace\MaiAccessibility\Service\AccessabilityAnalysisService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;

class LinkTextController
{
    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly AccessabilityAnalysisService $analysisService,
    ) {}

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $issues = $this->analysisService->findLinkTextIssues();
        $moduleTemplate->assign('issues', $issues);
        $moduleTemplate->assign('issueCount', count($issues));
        $moduleTemplate->assign('moduleName', 'Link Text');
        $moduleTemplate->assign('moduleDescription', 'This module finds links with non-descriptive text such as "click here", "read more", or "here". Descriptive link text is required by WCAG 2.1 Success Criterion 2.4.4 and 2.4.9.');
        return $moduleTemplate->renderResponse('LinkText/Index');
    }
}
