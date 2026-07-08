<?php

namespace Tests\Feature\Scans;

use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use DDD\Domain\Base\Users\User;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Sites\Site;
use DDD\Domain\Scans\Scan;

class ScanControllerTest extends TestCase
{
    /** @test */
    public function it_shows_a_scan_publicly_scoped_to_its_organization()
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $scan = Scan::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
        ]);

        // No authentication — the show route is public.
        $this->getJson("/api/{$organization->slug}/scans/{$scan->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $scan->id)
            ->assertJsonPath('data.run_id', $scan->run_id);
    }

    /** @test */
    public function it_does_not_resolve_a_scan_belonging_to_another_organization()
    {
        $organization = Organization::factory()->create();
        $other = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $other->id]);
        $scan = Scan::factory()->create([
            'organization_id' => $other->id,
            'site_id' => $site->id,
        ]);

        // scopeBindings() ties the scan to {organization}, so a mismatch 404s.
        $this->getJson("/api/{$organization->slug}/scans/{$scan->id}")
            ->assertNotFound();
    }

    /** @test */
    public function index_lists_scans_for_the_organization_excluding_single_page_scans()
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);

        $fullScan = Scan::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'is_single_page' => false,
        ]);
        Scan::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'is_single_page' => true,
        ]);

        Sanctum::actingAs(User::factory()->create(['organization_id' => $organization->id]));

        $this->getJson("/api/{$organization->slug}/scans")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $fullScan->id);
    }

    /** @test */
    public function index_requires_authentication()
    {
        $organization = Organization::factory()->create();

        $this->getJson("/api/{$organization->slug}/scans")
            ->assertUnauthorized();
    }
}
