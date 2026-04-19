<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Controller\Backend;

use Maispace\MaiBase\Controller\Backend\AbstractBackendController;
use Maispace\MaiBase\Controller\Backend\Traits\BackendCsvExportTrait;
use Maispace\MaiAccessibility\Check\CheckResult;
use Maispace\MaiAccessibility\Service\AccessibilityCheckService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

#[AsController]
final class AccessibilityController extends AbstractBackendController
{
    use BackendCsvExportTrait;

    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        IconFactory $iconFactory,
        private readonly AccessibilityCheckService $accessibilityCheckService,
        private readonly ConnectionPool $connectionPool,
    ) {
        parent::__construct($moduleTemplateFactory, $iconFactory);
    }

    public function indexAction(): ResponseInterface
    {
        $moduleTemplate = $this->createModuleTemplate();
        $this->addShortcutButton($moduleTemplate);

        $this->assignMultiple($moduleTemplate, [
            'pages' => $this->getCheckablePages(),
            'hasPages' => count($this->getCheckablePages()) > 0,
        ]);

        return $this->renderModuleResponse($moduleTemplate, 'Index');
    }

    public function checkAction(): ResponseInterface
    {
        $moduleTemplate = $this->createModuleTemplate();
        $this->addShortcutButton($moduleTemplate);

        $pages = $this->getCheckablePages();
        $pageUids = array_column($pages, 'uid');

        $resultsByPage = $this->accessibilityCheckService->checkPages($pageUids);

        $totalErrors = 0;
        $totalWarnings = 0;
        foreach ($resultsByPage as $results) {
            foreach ($results as $result) {
                if ($result->isError()) {
                    $totalErrors++;
                } elseif ($result->isWarning()) {
                    $totalWarnings++;
                }
            }
        }

        $this->assignMultiple($moduleTemplate, [
            'pages' => $pages,
            'resultsByPage' => $resultsByPage,
            'totalErrors' => $totalErrors,
            'totalWarnings' => $totalWarnings,
        ]);

        return $this->renderModuleResponse($moduleTemplate, 'Check');
    }

    public function exportCsvAction(): ResponseInterface
    {
        $pages = $this->getCheckablePages();
        $pageUids = array_column($pages, 'uid');
        $resultsByPage = $this->accessibilityCheckService->checkPages($pageUids);

        $rows = [['Page UID', 'Check', 'Severity', 'Message', 'Context']];
        foreach ($resultsByPage as $pageUid => $results) {
            foreach ($results as $result) {
                $rows[] = [
                    $pageUid,
                    $result->checkIdentifier,
                    $result->severity,
                    $result->message,
                    $result->context,
                ];
            }
        }

        return $this->csvDownloadResponse($rows, 'accessibility-report.csv');
    }

    private function getCheckablePages(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        return $queryBuilder
            ->select('uid', 'title', 'slug')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->eq('hidden', 0),
                $queryBuilder->expr()->eq('doktype', 1),
            )
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
