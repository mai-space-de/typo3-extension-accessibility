<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Controller\Backend;

use Maispace\MaiBase\Controller\Backend\AbstractBackendController;
use Maispace\MaiBase\Controller\Backend\Traits\BackendCsvExportTrait;
use Maispace\MaiAccessibility\Service\AccessibilityCheckService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Tree\Repository\PageTreeRepository;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Imaging\IconFactory;

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

        $rootPageUid = (int)($this->request->getQueryParams()['rootPageUid'] ?? 0);
        $rootPages = $this->getRootPages();
        $pages = $this->getCheckablePages($rootPageUid);

        $this->assignMultiple($moduleTemplate, [
            'pages' => $pages,
            'hasPages' => $pages !== [],
            'rootPages' => $rootPages,
            'rootPageUid' => $rootPageUid,
        ]);

        return $this->renderModuleResponse($moduleTemplate, 'Index');
    }

    public function checkAction(): ResponseInterface
    {
        $moduleTemplate = $this->createModuleTemplate();
        $this->addShortcutButton($moduleTemplate);

        $rootPageUid = (int)($this->request->getQueryParams()['rootPageUid'] ?? 0);
        $pages = $this->getCheckablePages($rootPageUid);
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
            'rootPageUid' => $rootPageUid,
        ]);

        return $this->renderModuleResponse($moduleTemplate, 'Check');
    }

    public function exportCsvAction(): ResponseInterface
    {
        $rootPageUid = (int)($this->request->getQueryParams()['rootPageUid'] ?? 0);
        $pages = $this->getCheckablePages($rootPageUid);
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

    private function getCheckablePages(int $rootPageUid = 0): array
    {
        if ($rootPageUid > 0) {
            $pageTreeRepository = new PageTreeRepository();
            $subtreePages = $pageTreeRepository->getFlattenedPages([$rootPageUid], 20);
            $rootPage = $this->fetchSinglePage($rootPageUid);
            if ($rootPage !== null) {
                array_unshift($subtreePages, $rootPage);
            }
            return array_values(array_filter($subtreePages, static fn(array $page): bool => (int)($page['doktype'] ?? 0) === 1));
        }

        return $this->fetchAllCheckablePages();
    }

    private function fetchAllCheckablePages(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        return $queryBuilder
            ->select('uid', 'title', 'slug', 'doktype')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('doktype', $queryBuilder->createNamedParameter(1, \PDO::PARAM_INT)),
            )
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    private function fetchSinglePage(int $uid): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $row = $queryBuilder
            ->select('uid', 'title', 'slug', 'doktype')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchAssociative();

        return $row ?: null;
    }

    private function getRootPages(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        return $queryBuilder
            ->select('uid', 'title')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('doktype', $queryBuilder->createNamedParameter(1, \PDO::PARAM_INT)),
            )
            ->orderBy('sorting')
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
