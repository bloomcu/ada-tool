<?php

namespace Tests\Feature\Console;

use Tests\TestCase;
use DDD\Domain\Scans\Scan;
use DDD\Domain\Pages\Page;

class ExportScanIssuesTest extends TestCase
{
    /** @test */
    public function it_outputs_the_scan_issue_json()
    {
        $scan = Scan::factory()->create(['violation_count' => 1, 'warning_count' => 0]);
        Page::factory()->create([
            'scan_id' => $scan->id,
            'violation_count' => 1,
            'results' => json_encode(['eval_url' => 'https://example.com', 'rule_results' => [
                ['rule_id' => 'IMAGE_1', 'elements_violation' => 1, 'elements_warning' => 0,
                    'element_results' => [['element_identifier' => 'img: a', 'result_value_nls' => 'V']]],
            ]]),
        ]);

        $this->artisan('scans:export-issues', ['scan' => $scan->id])
            ->assertExitCode(0)
            ->expectsOutputToContain('IMAGE_1');
    }

    /** @test */
    public function it_writes_to_a_file_when_path_is_given()
    {
        $scan = Scan::factory()->create();
        Page::factory()->create([
            'scan_id' => $scan->id,
            'violation_count' => 1,
            'results' => json_encode(['eval_url' => 'https://example.com', 'rule_results' => [
                ['rule_id' => 'IMAGE_1', 'elements_violation' => 1, 'elements_warning' => 0,
                    'element_results' => [['element_identifier' => 'img: a', 'result_value_nls' => 'V']]],
            ]]),
        ]);

        $path = storage_path('app/test-scan-export.json');
        @unlink($path);

        $this->artisan('scans:export-issues', ['scan' => $scan->id, '--path' => $path])
            ->assertExitCode(0)
            ->expectsOutputToContain('Exported scan');

        $this->assertFileExists($path);
        $decoded = json_decode(file_get_contents($path), true);
        $this->assertSame(1, $decoded['scan']['pages_with_issues']);
        $this->assertSame('IMAGE_1', $decoded['pages'][0]['issues'][0]['rule_id']);

        @unlink($path);
    }

    /** @test */
    public function it_errors_for_an_unknown_scan()
    {
        $this->artisan('scans:export-issues', ['scan' => 999999])
            ->assertExitCode(1)
            ->expectsOutputToContain('No scan found');
    }
}
