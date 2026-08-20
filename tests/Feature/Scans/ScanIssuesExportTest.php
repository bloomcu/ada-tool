<?php

namespace Tests\Feature\Scans;

use Tests\TestCase;
use DDD\Domain\Scans\Scan;
use DDD\Domain\Pages\Page;
use DDD\Domain\Scans\ScanIssuesExport;

class ScanIssuesExportTest extends TestCase
{
    /**
     * @param  array<int, array<string, mixed>>  $ruleResults
     */
    private function results(array $ruleResults, string $url = 'https://example.com/x'): string
    {
        return json_encode(['eval_url' => $url, 'rule_results' => $ruleResults]);
    }

    /** @test */
    public function it_exports_only_pages_with_issues_plus_a_scan_summary()
    {
        $scan = Scan::factory()->create([
            'violation_count' => 3,
            'warning_count' => 1,
            'violation_count_pages' => 1,
            'warning_count_pages' => 1,
        ]);

        Page::factory()->create([
            'scan_id' => $scan->id,
            'title' => 'Contact',
            'violation_count' => 3,
            'warning_count' => 1,
            'results' => $this->results([
                ['rule_id' => 'IMAGE_1', 'rule_summary' => 'Images need alt text', 'rule_required' => true, 'elements_violation' => 3, 'elements_warning' => 0,
                    'element_results' => [['element_identifier' => 'img: a', 'result_value_nls' => 'V']]],
            ]),
        ]);

        // Clean page — only a passing rule → must be omitted from the export.
        Page::factory()->create([
            'scan_id' => $scan->id,
            'title' => 'Clean',
            'violation_count' => 0,
            'warning_count' => 0,
            'results' => $this->results([
                ['rule_id' => 'AUDIO_1', 'elements_violation' => 0, 'elements_warning' => 0],
            ]),
        ]);

        $out = (new ScanIssuesExport())->export($scan);

        $this->assertSame($scan->id, $out['scan']['id']);
        $this->assertSame(2, $out['scan']['pages_total']);
        $this->assertSame(1, $out['scan']['pages_with_issues']);
        $this->assertSame(3, $out['scan']['violation_count']);

        $this->assertCount(1, $out['pages']);
        $this->assertSame('Contact', $out['pages'][0]['title']);
        $this->assertSame('IMAGE_1', $out['pages'][0]['issues'][0]['rule_id']);
    }

    /** @test */
    public function it_does_not_cap_elements_per_page()
    {
        $elements = [];
        for ($i = 0; $i < 30; $i++) {
            $elements[] = ['element_identifier' => "E{$i}", 'result_value_nls' => 'V'];
        }

        $scan = Scan::factory()->create();
        Page::factory()->create([
            'scan_id' => $scan->id,
            'violation_count' => 30,
            'results' => $this->results([
                ['rule_id' => 'IMAGE_1', 'elements_violation' => 30, 'elements_warning' => 0, 'element_results' => $elements],
            ]),
        ]);

        $out = (new ScanIssuesExport())->export($scan);

        $issue = $out['pages'][0]['issues'][0];
        $this->assertCount(30, $issue['elements']);
        $this->assertFalse($issue['elements_truncated']);
    }

    /** @test */
    public function it_orders_pages_worst_first()
    {
        $scan = Scan::factory()->create();
        Page::factory()->create(['scan_id' => $scan->id, 'title' => 'Few', 'violation_count' => 2,
            'results' => $this->results([['rule_id' => 'R', 'elements_violation' => 2, 'elements_warning' => 0]])]);
        Page::factory()->create(['scan_id' => $scan->id, 'title' => 'Many', 'violation_count' => 9,
            'results' => $this->results([['rule_id' => 'R', 'elements_violation' => 9, 'elements_warning' => 0]])]);

        $out = (new ScanIssuesExport())->export($scan);

        $this->assertSame(['Many', 'Few'], array_column($out['pages'], 'title'));
    }

    /** @test */
    public function it_returns_an_empty_pages_list_for_a_scan_with_no_issues()
    {
        $scan = Scan::factory()->create();
        Page::factory()->create([
            'scan_id' => $scan->id,
            'results' => $this->results([['rule_id' => 'AUDIO_1', 'elements_violation' => 0, 'elements_warning' => 0]]),
        ]);

        $out = (new ScanIssuesExport())->export($scan);

        $this->assertSame(0, $out['scan']['pages_with_issues']);
        $this->assertSame([], $out['pages']);
    }
}
