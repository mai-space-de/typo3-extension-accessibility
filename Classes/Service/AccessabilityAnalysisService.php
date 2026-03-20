<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Service;

use TYPO3\CMS\Core\Database\ConnectionPool;

class AccessabilityAnalysisService
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * Find content elements with images that have missing or empty alt text.
     */
    public function findMissingAltTexts(): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $qb->getRestrictions()->removeAll();

        $rows = $qb
            ->select(
                'tc.uid',
                'tc.pid',
                'tc.header',
                'tc.CType',
                'sfr.uid_local',
                'sfr.alternative',
                'sf.name as file_name',
                'p.title as page_title',
            )
            ->from('sys_file_reference', 'sfr')
            ->join('sfr', 'tt_content', 'tc', $qb->expr()->eq('sfr.uid_foreign', $qb->quoteIdentifier('tc.uid')))
            ->join('sfr', 'sys_file', 'sf', $qb->expr()->eq('sfr.uid_local', $qb->quoteIdentifier('sf.uid')))
            ->leftJoin('tc', 'pages', 'p', $qb->expr()->eq('tc.pid', $qb->quoteIdentifier('p.uid')))
            ->where(
                $qb->expr()->eq('sfr.deleted', $qb->createNamedParameter(0, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)),
                $qb->expr()->eq('sfr.hidden', $qb->createNamedParameter(0, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)),
                $qb->expr()->eq('tc.deleted', $qb->createNamedParameter(0, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)),
                $qb->expr()->eq('tc.hidden', $qb->createNamedParameter(0, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)),
                $qb->expr()->eq('p.deleted', $qb->createNamedParameter(0, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)),
                $qb->expr()->eq('sfr.tablenames', $qb->createNamedParameter('tt_content')),
                $qb->expr()->eq('sf.type', $qb->createNamedParameter(2, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)),
                $qb->expr()->or(
                    $qb->expr()->eq('sfr.alternative', $qb->createNamedParameter('')),
                    $qb->expr()->isNull('sfr.alternative'),
                ),
            )
            ->orderBy('tc.pid')
            ->addOrderBy('tc.uid')
            ->executeQuery()
            ->fetchAllAssociative();

        $issues = [];
        foreach ($rows as $row) {
            $issues[] = [
                'uid'       => $row['uid'],
                'pid'       => $row['pid'],
                'pageTitle' => $row['page_title'] ?? '',
                'header'    => $row['header'] ?: ('Image: ' . $row['file_name']),
                'issue'     => sprintf('Image "%s" has no alt text.', $row['file_name']),
                'severity'  => 'error',
            ];
        }
        return $issues;
    }

    /**
     * Analyse heading structure per page and report issues.
     */
    public function findHeadingStructureIssues(): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $qb->getRestrictions()->removeAll();

        $rows = $qb
            ->select('tc.uid', 'tc.pid', 'tc.header', 'tc.header_layout', 'tc.sorting', 'p.title as page_title')
            ->from('tt_content', 'tc')
            ->leftJoin('tc', 'pages', 'p', $qb->expr()->eq('tc.pid', $qb->quoteIdentifier('p.uid')))
            ->where(
                $qb->expr()->eq('tc.deleted', $qb->createNamedParameter(0, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)),
                $qb->expr()->eq('tc.hidden', $qb->createNamedParameter(0, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)),
                $qb->expr()->eq('p.deleted', $qb->createNamedParameter(0, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)),
                $qb->expr()->neq('tc.header', $qb->createNamedParameter('')),
                $qb->expr()->neq('tc.header_layout', $qb->createNamedParameter(100, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)),
            )
            ->orderBy('tc.pid')
            ->addOrderBy('tc.sorting')
            ->executeQuery()
            ->fetchAllAssociative();

        // Group by page
        $byPage = [];
        foreach ($rows as $row) {
            $pid = $row['pid'];
            if (!isset($byPage[$pid])) {
                $byPage[$pid] = [
                    'pageTitle' => $row['page_title'] ?? 'Page ' . $pid,
                    'elements'  => [],
                ];
            }
            $byPage[$pid]['elements'][] = $row;
        }

        $issues = [];
        foreach ($byPage as $pid => $pageData) {
            $headings = array_filter($pageData['elements'], static function (array $el): bool {
                $layout = (int)$el['header_layout'];
                return $layout >= 1 && $layout <= 6;
            });
            $headings = array_values($headings);

            if (empty($headings)) {
                continue;
            }

            $levels = array_map(static fn(array $h): int => (int)$h['header_layout'], $headings);
            $h1Count = count(array_filter($levels, static fn(int $l): bool => $l === 1));

            if ($h1Count === 0) {
                $first = $headings[0];
                $issues[] = [
                    'uid'       => $first['uid'],
                    'pid'       => $pid,
                    'pageTitle' => $pageData['pageTitle'],
                    'header'    => $first['header'],
                    'issue'     => 'Page is missing an H1 heading.',
                    'severity'  => 'error',
                ];
            } elseif ($h1Count > 1) {
                foreach ($headings as $h) {
                    if ((int)$h['header_layout'] === 1) {
                        $issues[] = [
                            'uid'       => $h['uid'],
                            'pid'       => $pid,
                            'pageTitle' => $pageData['pageTitle'],
                            'header'    => $h['header'],
                            'issue'     => 'Page has multiple H1 headings (' . $h1Count . ' found).',
                            'severity'  => 'error',
                        ];
                    }
                }
            }

            // Check for skipped heading levels
            for ($i = 1; $i < count($levels); $i++) {
                $prev = $levels[$i - 1];
                $curr = $levels[$i];
                if ($curr > $prev + 1) {
                    $issues[] = [
                        'uid'       => $headings[$i]['uid'],
                        'pid'       => $pid,
                        'pageTitle' => $pageData['pageTitle'],
                        'header'    => $headings[$i]['header'],
                        'issue'     => sprintf('Heading level skipped: H%d follows H%d.', $curr, $prev),
                        'severity'  => 'warning',
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * Find ARIA attribute issues in bodytext content.
     */
    public function findAriaIssues(): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $qb->getRestrictions()->removeAll();

        $rows = $qb
            ->select('tc.uid', 'tc.pid', 'tc.header', 'tc.bodytext', 'p.title as page_title')
            ->from('tt_content', 'tc')
            ->leftJoin('tc', 'pages', 'p', $qb->expr()->eq('tc.pid', $qb->quoteIdentifier('p.uid')))
            ->where(
                $qb->expr()->eq('tc.deleted', $qb->createNamedParameter(0, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)),
                $qb->expr()->eq('tc.hidden', $qb->createNamedParameter(0, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)),
                $qb->expr()->eq('p.deleted', $qb->createNamedParameter(0, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)),
                $qb->expr()->neq('tc.bodytext', $qb->createNamedParameter('')),
                $qb->expr()->isNotNull('tc.bodytext'),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $issues = [];
        foreach ($rows as $row) {
            $bodytext = $row['bodytext'] ?? '';
            if (empty($bodytext)) {
                continue;
            }

            // Check for <img> tags without alt attribute or empty alt in RTE content
            if (preg_match_all('/<img(?=[^>])[^>]*>/i', $bodytext, $imgMatches)) {
                foreach ($imgMatches[0] as $imgTag) {
                    $hasAlt = (bool)preg_match('/\balt\s*=/i', $imgTag);
                    $hasEmptyAlt = (bool)preg_match('/\balt\s*=\s*(["\'])(\s*)\1/i', $imgTag);
                    $hasRole = (bool)preg_match('/\brole\s*=/i', $imgTag);

                    if (!$hasAlt && !$hasRole) {
                        $issues[] = [
                            'uid'       => $row['uid'],
                            'pid'       => $row['pid'],
                            'pageTitle' => $row['page_title'] ?? '',
                            'header'    => $row['header'],
                            'issue'     => 'Inline image in RTE content is missing the alt attribute.',
                            'severity'  => 'error',
                        ];
                        break;
                    }
                    if ($hasEmptyAlt) {
                        // Empty alt is valid for decorative images, but flag as warning
                        $issues[] = [
                            'uid'       => $row['uid'],
                            'pid'       => $row['pid'],
                            'pageTitle' => $row['page_title'] ?? '',
                            'header'    => $row['header'],
                            'issue'     => 'Inline image in RTE content has an empty alt attribute — confirm it is decorative.',
                            'severity'  => 'warning',
                        ];
                        break;
                    }
                }
            }

            // Check for <a> tags used as buttons (role="button") but lacking an href
            if (preg_match_all('/<a(?=[^>])[^>]*>/i', $bodytext, $linkMatches)) {
                foreach ($linkMatches[0] as $linkTag) {
                    if (preg_match('/role\s*=\s*["\']button["\']/', $linkTag) && !preg_match('/\bhref\s*=/i', $linkTag)) {
                        $issues[] = [
                            'uid'       => $row['uid'],
                            'pid'       => $row['pid'],
                            'pageTitle' => $row['page_title'] ?? '',
                            'header'    => $row['header'],
                            'issue'     => 'Anchor with role="button" is missing an href attribute.',
                            'severity'  => 'warning',
                        ];
                        break;
                    }
                }
            }
        }

        return $issues;
    }

    /**
     * Find non-descriptive link text in bodytext content.
     */
    public function findLinkTextIssues(): array
    {
        $nonDescriptivePhrases = [
            'click here',
            'here',
            'read more',
            'more',
            'link',
            'this link',
            'click',
            'continue',
            'weiterlesen',
            'mehr',
            'hier',
            'hier klicken',
        ];

        $qb = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $qb->getRestrictions()->removeAll();

        $rows = $qb
            ->select('tc.uid', 'tc.pid', 'tc.header', 'tc.bodytext', 'p.title as page_title')
            ->from('tt_content', 'tc')
            ->leftJoin('tc', 'pages', 'p', $qb->expr()->eq('tc.pid', $qb->quoteIdentifier('p.uid')))
            ->where(
                $qb->expr()->eq('tc.deleted', $qb->createNamedParameter(0, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)),
                $qb->expr()->eq('tc.hidden', $qb->createNamedParameter(0, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)),
                $qb->expr()->eq('p.deleted', $qb->createNamedParameter(0, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)),
                $qb->expr()->neq('tc.bodytext', $qb->createNamedParameter('')),
                $qb->expr()->isNotNull('tc.bodytext'),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $issues = [];
        foreach ($rows as $row) {
            $bodytext = $row['bodytext'] ?? '';
            if (empty($bodytext)) {
                continue;
            }

            if (!preg_match_all('/<a\b[^>]*>(.*?)<\/a>/is', $bodytext, $matches, PREG_SET_ORDER)) {
                continue;
            }

            $foundPhrase = $this->findNonDescriptiveLinkText($matches, $nonDescriptivePhrases);
            if ($foundPhrase !== null) {
                $issues[] = [
                    'uid'       => $row['uid'],
                    'pid'       => $row['pid'],
                    'pageTitle' => $row['page_title'] ?? '',
                    'header'    => $row['header'],
                    'issue'     => sprintf('Non-descriptive link text found: "%s".', $foundPhrase),
                    'severity'  => 'warning',
                ];
            }
        }

        return $issues;
    }

    /**
     * Return the first non-descriptive link text found in the given anchor matches, or null.
     *
     * @param list<array<int, string>> $matches     Results from preg_match_all with PREG_SET_ORDER
     * @param list<string>             $phraseList  Lower-case phrases to flag
     */
    private function findNonDescriptiveLinkText(array $matches, array $phraseList): ?string
    {
        foreach ($matches as $match) {
            $linkText = trim(strip_tags($match[1]));
            if (in_array(mb_strtolower($linkText), $phraseList, true)) {
                return $linkText;
            }
        }
        return null;
    }
}
