<?php

namespace Tests\Feature\Scans;

use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use DDD\Domain\Base\Users\User;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Sites\Site;
use DDD\Domain\Scans\Scan;
use DDD\Domain\Pages\Page;

class ScanIssuesExportControllerTest extends TestCase
{
    private function scanWithIssue(Organization $organization): Scan
    {
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $scan = Scan::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'violation_count' => 1,
        ]);
        Page::factory()->create([
            'scan_id' => $scan->id,
            'violation_count' => 1,
            'results' => json_encode(['eval_url' => 'https://example.com', 'rule_results' => [
                ['rule_id' => 'IMAGE_1', 'elements_violation' => 1, 'elements_warning' => 0,
                    'element_results' => [['element_identifier' => 'img: a', 'result_value_nls' => 'V']]],
            ]]),
        ]);

        return $scan;
    }

    /** @test */
    public function it_downloads_the_scan_issues_as_a_json_file()
    {
        $organization = Organization::factory()->create();
        $scan = $this->scanWithIssue($organization);

        Sanctum::actingAs(User::factory()->create(['organization_id' => $organization->id]));

        $response = $this->get("/api/{$organization->slug}/scans/{$scan->id}/issues/export");

        $response->assertOk()
            ->assertDownload("scan-{$scan->id}-issues.json");

        $decoded = json_decode($response->streamedContent(), true);
        $this->assertSame($scan->id, $decoded['scan']['id']);
        $this->assertSame(1, $decoded['scan']['pages_with_issues']);
        $this->assertSame('IMAGE_1', $decoded['pages'][0]['issues'][0]['rule_id']);
    }

    /** @test */
    public function it_requires_authentication()
    {
        $organization = Organization::factory()->create();
        $scan = $this->scanWithIssue($organization);

        $this->getJson("/api/{$organization->slug}/scans/{$scan->id}/issues/export")
            ->assertUnauthorized();
    }

    /** @test */
    public function it_does_not_export_a_scan_belonging_to_another_organization()
    {
        $organization = Organization::factory()->create();
        $other = Organization::factory()->create();
        $scan = $this->scanWithIssue($other);

        Sanctum::actingAs(User::factory()->create(['organization_id' => $organization->id]));

        // scopeBindings() ties {scan} to {organization}, so a mismatch 404s.
        $this->getJson("/api/{$organization->slug}/scans/{$scan->id}/issues/export")
            ->assertNotFound();
    }
}
