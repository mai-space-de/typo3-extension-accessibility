<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Controller\Backend;

use Maispace\MaiBase\Controller\Backend\AbstractBackendController;
use Maispace\MaiBase\Controller\Backend\Traits\BackendCsvExportTrait;
use Maispace\MaiAccessibility\Service\AccessibilityCheckService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
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
        $this->addShortcutButton($moduleTemplate, 'mai_accessibility', 'Accessibility');

        $selectedPageUid = (int) ($this->request->getQueryParams()['selectedPageUid'] ?? 0);
        $selectedPageTitle = '';
        $pages = [];

        if ($selectedPageUid > 0) {
            $page = $this->fetchSinglePage($selectedPageUid);
            $selectedPageTitle = $page['title'] ?? '';
            $pages = $this->getCheckablePages($selectedPageUid);
        }

        $this->assignMultiple($moduleTemplate, [
            'pages' => $pages,
            'hasPages' => $pages !== [],
            'selectedPageUid' => $selectedPageUid,
            'selectedPageTitle' => $selectedPageTitle,
        ]);

        return $this->renderModuleResponse($moduleTemplate, 'Index');
    }

    public function checkAction(): ResponseInterface
    {
        $moduleTemplate = $this->createModuleTemplate();
        $this->addShortcutButton($moduleTemplate, 'mai_accessibility', 'Accessibility');

        $selectedPageUid = (int) ($this->request->getQueryParams()['selectedPageUid'] ?? 0);
        $pages = $this->getCheckablePages($selectedPageUid);
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
            'selectedPageUid' => $selectedPageUid,
        ]);

        return $this->renderModuleResponse($moduleTemplate, 'Check');
    }

    public function exportCsvAction(): ResponseInterface
    {
        $selectedPageUid = (int) ($this->request->getQueryParams()['selectedPageUid'] ?? 0);
        $pages = $this->getCheckablePages($selectedPageUid);
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

    /**
     * Resolves all page UIDs in the subtree of the given parent using iterative
     * breadth-first traversal. This ensures truly recursive resolution without
     * depth limits, unlike the PageTreeRepository::getFlattenedPages approach.
     *
     * @return int[] Sorted list of page UIDs including the parent.
     */
    private function resolveSubtreePageIds(int $parentUid): array
    {
        $collected = [];
        $stack = [$parentUid];

        while ($stack !== []) {
            $currentUid = array_shift($stack);
            $collected[] = $currentUid;

            $children = $this->fetchChildPageIds($currentUid);
            foreach ($children as $childUid) {
                if (!in_array($childUid, $collected, true) && !in_array($childUid, $stack, true)) {
                    $stack[] = $childUid;
                }
            }
        }

        sort($collected);
        return $collected;
    }

    private function fetchChildPageIds(int $parentUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $rows = $queryBuilder
            ->select('uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($parentUid, \Doctrine\DBAL\ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER)),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(intval(...), array_column($rows, 'uid'));
    }

    private function getCheckablePages(int $selectedPageUid = 0): array
    {
        if ($selectedPageUid > 0) {
            $pageIds = $this->resolveSubtreePageIds($selectedPageUid);

            return $this->fetchPagesByUids($pageIds);
        }

        return $this->fetchAllCheckablePages();
    }

    private function fetchPagesByUids(array $uids): array
    {
        if ($uids === []) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        return $queryBuilder
            ->select('uid', 'title', 'slug', 'doktype')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->in('uid', $uids),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('doktype', $queryBuilder->createNamedParameter(1, \Doctrine\DBAL\ParameterType::INTEGER)),
            )
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    private function fetchAllCheckablePages(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        return $queryBuilder
            ->select('uid', 'title', 'slug', 'doktype')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('doktype', $queryBuilder->createNamedParameter(1, \Doctrine\DBAL\ParameterType::INTEGER)),
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
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, \Doctrine\DBAL\ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER)),
            )
            ->executeQuery()
            ->fetchAssociative();

        return $row ?: null;
    }
}
