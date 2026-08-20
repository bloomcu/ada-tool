<?php

namespace DDD\Domain\Scans;

use DDD\Domain\Pages\Page;
use DDD\Domain\Pages\PageIssueFormatter;

/**
 * Aggregates a scan's most-recent page results into a single trimmed, LLM-parsible
 * export: a scan-level summary plus, for every page that still has findings, its
 * violations/warnings (uncapped) via {@see PageIssueFormatter}.
 *
 * "Most recent per page" is inherent to the data model — a completed rescan
 * overwrites `pages.results` in place — so reading each page under the scan gives
 * the latest imported results with no versioning (see docs/Plan.md). Pages with no
 * remaining issues are omitted to keep the payload focused on what needs work.
 */
class ScanIssuesExport
{
    /**
     * Pages processed per DB round-trip. A page's raw `results` blob is ~117 KB, so
     * chunking bounds peak memory — we never hold a whole large scan at once.
     */
    private const CHUNK = 100;

    public function __construct(
        private PageIssueFormatter $formatter = new PageIssueFormatter(),
    ) {}

    /**
     * Build the export payload for a scan.
     *
     * @return array<string, mixed>
     */
    public function export(Scan $scan): array
    {
        $pagesTotal = $scan->pages()->count();

        // Do NOT `ORDER BY violation_count` in SQL: with `SELECT *` that pulls the
        // large `results` TEXT column into MySQL's filesort, which overflows the sort
        // buffer on big scans (SQLSTATE[HY001] 1038 "Out of sort memory"). chunkById()
        // pages by the primary key (indexed, no filesort) and bounds memory; we order
        // worst-first in PHP afterwards.
        $exportedPages = [];
        $scan->pages()
            ->select('id', 'title', 'violation_count', 'warning_count', 'results')
            ->chunkById(self::CHUNK, function ($pages) use (&$exportedPages): void {
                foreach ($pages as $page) {
                    $exported = $this->exportPage($page);
                    if ($exported['issue_count'] > 0) {
                        $exportedPages[] = $exported;
                    }
                }
            });

        usort(
            $exportedPages,
            fn (array $a, array $b): int => ($b['violation_count'] ?? 0) <=> ($a['violation_count'] ?? 0),
        );

        return [
            'scan' => [
                'id' => $scan->id,
                'site' => $scan->site?->domain,
                'status' => $scan->status,
                'violation_count' => $scan->violation_count,
                'warning_count' => $scan->warning_count,
                'violation_count_pages' => $scan->violation_count_pages,
                'warning_count_pages' => $scan->warning_count_pages,
                'pages_total' => $pagesTotal,
                'pages_with_issues' => count($exportedPages),
            ],
            'pages' => $exportedPages,
        ];
    }

    /**
     * Shape one page's trimmed issues for the export.
     *
     * @return array<string, mixed>
     */
    private function exportPage(Page $page): array
    {
        $results = is_array($page->results) ? $page->results : [];
        $formatted = $this->formatter->format($results);

        return [
            'page_id' => $page->id,
            'url' => $results['eval_url'] ?? null,
            'title' => $page->title,
            'violation_count' => $page->violation_count,
            'warning_count' => $page->warning_count,
            'issue_count' => $formatted['issue_count'],
            'issues' => $formatted['issues'],
        ];
    }
}
