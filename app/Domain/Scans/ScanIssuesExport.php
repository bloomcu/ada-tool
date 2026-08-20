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
        $pages = $scan->pages()->orderByDesc('violation_count')->get();

        $exportedPages = $pages
            ->map(fn (Page $page): array => $this->exportPage($page))
            ->filter(fn (array $page): bool => $page['issue_count'] > 0)
            ->values()
            ->all();

        return [
            'scan' => [
                'id' => $scan->id,
                'site' => $scan->site?->domain,
                'status' => $scan->status,
                'violation_count' => $scan->violation_count,
                'warning_count' => $scan->warning_count,
                'violation_count_pages' => $scan->violation_count_pages,
                'warning_count_pages' => $scan->warning_count_pages,
                'pages_total' => $pages->count(),
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
        $results = $page->results ?? [];
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
