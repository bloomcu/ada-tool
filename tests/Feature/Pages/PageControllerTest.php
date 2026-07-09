<?php

namespace Tests\Feature\Pages;

use Tests\TestCase;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Sites\Site;
use DDD\Domain\Scans\Scan;
use DDD\Domain\Pages\Page;

class PageControllerTest extends TestCase
{
    /**
     * Build an org → site → scan → page chain and return the pieces.
     */
    private function chain(): array
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $scan = Scan::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
        ]);
        $page = Page::factory()->create([
            'scan_id' => $scan->id,
            'results' => json_encode(['violations' => [], 'warnings' => []]),
        ]);

        return [$organization, $scan, $page];
    }

    /** @test */
    public function it_shows_a_page_publicly_with_decoded_results()
    {
        [$organization, $scan, $page] = $this->chain();

        // The route is public (no auth).
        $this->getJson("/api/{$organization->slug}/scans/{$scan->id}/pages/{$page->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $page->id)
            ->assertJsonPath('data.title', $page->title)
            // `results` is json-decoded by the model cast, so it comes back as an object.
            ->assertJsonPath('data.results.violations', [])
            ->assertJsonPath('data.results.warnings', []);
    }

    /** @test */
    public function it_does_not_resolve_a_page_from_a_different_scan()
    {
        [$organization, $scan, $page] = $this->chain();

        // A second scan under the same org that does NOT own the page.
        $otherScan = Scan::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => Site::factory()->create(['organization_id' => $organization->id])->id,
        ]);

        // scopeBindings ties {page} to {scan}, so the mismatch 404s.
        $this->getJson("/api/{$organization->slug}/scans/{$otherScan->id}/pages/{$page->id}")
            ->assertNotFound();
    }

    /** @test */
    public function it_does_not_resolve_a_scan_from_a_different_organization()
    {
        [$organization, $scan, $page] = $this->chain();
        $otherOrg = Organization::factory()->create();

        $this->getJson("/api/{$otherOrg->slug}/scans/{$scan->id}/pages/{$page->id}")
            ->assertNotFound();
    }
}
