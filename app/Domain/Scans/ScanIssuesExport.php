<?php

namespace DDD\Domain\Scans;

use DDD\Domain\Pages\Page;
use DDD\Domain\Pages\PageIssueFormatter;
use Illuminate\Support\Facades\DB;

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
     * Ids streamed per `lazyById` batch. We select only small columns here (never the
     * ~117 KB `results` blob) and read each page's results one at a time — so neither
     * MySQL's sort buffer nor PHP ever holds a whole scan's blobs.
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

        // The ~117 KB `results` TEXT column must NEVER appear in a sorted/paged query:
        // MySQL's filesort packs whole rows into the sort buffer and overflows it on big
        // scans (SQLSTATE[HY001] 1038 "Out of sort memory"). So stream only small columns
        // ordered by the indexed primary key via lazyById(), and read each page's results
        // ONE AT A TIME as a point lookup (no sort). Worst-first ordering happens in PHP
        // afterwards on the trimmed (small) data.
        $exportedPages = [];
        $scan->pages()
            ->select('id', 'title', 'violation_count', 'warning_count')
            ->lazyById(self::CHUNK)
            ->each(function (Page $page) use (&$exportedPages): void {
                $raw = DB::table('pages')->where('id', $page->id)->value('results');
                $decoded = json_decode($raw ?? '', true);

                $exported = $this->exportPage($page, is_array($decoded) ? $decoded : []);
                if ($exported['issue_count'] > 0) {
                    $exportedPages[] = $exported;
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
    /**
     * @param  array<string, mixed>  $results  Decoded `page.results` (fetched separately so the
     *                                          blob never rides along in a sorted/paged query).
     */
    private function exportPage(Page $page, array $results): array
    {
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
