<?php

namespace Tests\Feature\Scans;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use DDD\Domain\Base\Users\User;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Sites\Site;
use DDD\Domain\Scans\Scan;
use DDD\Domain\Pages\Page;

class ScanImportControllerTest extends TestCase
{
    /**
     * Fake the two Apify dataset endpoints import() uses: the meta (itemCount)
     * and the paginated items. Order matters — the more specific /items pattern
     * must come first.
     */
    private function fakeApifyDataset(array $items): void
    {
        Http::fake([
            'api.apify.com/v2/datasets/*/items*' => Http::response($items),
            'api.apify.com/v2/datasets/*' => Http::response([
                'data' => ['itemCount' => count($items)],
            ]),
        ]);
    }

    /** @test */
    public function it_imports_the_dataset_into_pages_and_tallies_scan_counts()
    {
        $this->fakeApifyDataset([
            [
                'url' => 'https://example.com/',
                'title' => 'Home',
                'results' => json_encode(['rule_results' => [
                    ['elements_violation' => 2, 'elements_warning' => 1],
                    ['elements_violation' => 0, 'elements_warning' => 3],
                ]]),
            ],
            [
                'url' => 'https://example.com/about',
                'title' => 'About',
                'results' => json_encode(['rule_results' => [
                    ['elements_violation' => 0, 'elements_warning' => 0],
                ]]),
            ],
        ]);

        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $scan = Scan::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
        ]);

        Sanctum::actingAs(User::factory()->create(['organization_id' => $organization->id]));

        $this->getJson("/api/{$organization->slug}/scans/{$scan->id}/import")
            ->assertOk();

        // One Page per dataset item, with per-page tallies.
        $this->assertDatabaseHas('pages', [
            'scan_id' => $scan->id,
            'title' => 'Home',
            'violation_count' => 2,
            'warning_count' => 4,
        ]);
        $this->assertDatabaseHas('pages', [
            'scan_id' => $scan->id,
            'title' => 'About',
            'violation_count' => 0,
            'warning_count' => 0,
        ]);

        // Scan-level totals: 2 violations, 4 warnings, across 1 violating page / 1 warning page.
        $this->assertDatabaseHas('scans', [
            'id' => $scan->id,
            'violation_count' => 2,
            'warning_count' => 4,
            'violation_count_pages' => 1,
            'warning_count_pages' => 1,
        ]);
    }

    /** @test */
    public function import_with_an_empty_dataset_creates_no_pages_and_zeroes_counts()
    {
        // itemCount 0 -> the paginated loop never runs (foreach is empty-safe).
        $this->fakeApifyDataset([]);

        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $scan = Scan::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
        ]);

        Sanctum::actingAs(User::factory()->create(['organization_id' => $organization->id]));

        $this->getJson("/api/{$organization->slug}/scans/{$scan->id}/import")
            ->assertOk();

        $this->assertDatabaseCount('pages', 0);
        $this->assertDatabaseHas('scans', [
            'id' => $scan->id,
            'violation_count' => 0,
            'warning_count' => 0,
        ]);
    }

    /**
     * Set up a page that has been queued for a single-page rescan (rescan_id
     * points at a single-page Scan), returning the route URL.
     */
    private function rescanImportUrl(array $rescanItems): array
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $scan = Scan::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'violation_count' => 5,
            'warning_count' => 5,
            'violation_count_pages' => 2,
            'warning_count_pages' => 2,
        ]);
        $rescan = Scan::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'is_single_page' => true,
        ]);
        $page = Page::factory()->create([
            'scan_id' => $scan->id,
            'rescan_id' => $rescan->id,
            'violation_count' => 0,
            'warning_count' => 0,
        ]);

        Http::fake([
            'api.apify.com/v2/datasets/*/items*' => Http::response($rescanItems),
            'api.apify.com/v2/datasets/*' => Http::response(['data' => ['itemCount' => count($rescanItems)]]),
        ]);

        Sanctum::actingAs(User::factory()->create(['organization_id' => $organization->id]));

        $url = "/api/{$organization->slug}/sites/{$site->id}/scans/{$scan->id}/page/{$page->id}/rescan/import";

        return [$url, $scan, $page];
    }

    /** @test */
    public function it_reimports_a_single_page_and_clears_the_rescan_link()
    {
        [$url, $scan, $page] = $this->rescanImportUrl([
            [
                'url' => 'https://example.com/contact',
                'title' => 'Contact',
                'results' => json_encode(['rule_results' => [
                    ['elements_violation' => 1, 'elements_warning' => 2],
                ]]),
            ],
        ]);

        $this->getJson($url)->assertOk();

        // Page picks up the rescan's per-page counts and its rescan link is cleared.
        $this->assertDatabaseHas('pages', [
            'id' => $page->id,
            'violation_count' => 1,
            'warning_count' => 2,
            'rescan_id' => null,
        ]);

        // Scan totals adjust: 5 - 0 + 1 = 6 violations, 5 - 0 + 2 = 7 warnings.
        $this->assertDatabaseHas('scans', [
            'id' => $scan->id,
            'violation_count' => 6,
            'warning_count' => 7,
        ]);
    }

    /** @test */
    public function it_handles_a_rescan_page_with_no_rule_results()
    {
        // Item present but its results carry no rule_results — must not crash.
        [$url, $scan, $page] = $this->rescanImportUrl([
            [
                'url' => 'https://example.com/contact',
                'title' => 'Contact',
                'results' => json_encode(['rule_results' => []]),
            ],
        ]);

        $this->getJson($url)->assertOk();

        $this->assertDatabaseHas('pages', [
            'id' => $page->id,
            'violation_count' => 0,
            'warning_count' => 0,
            'rescan_id' => null,
        ]);
    }

    /** @test */
    public function it_handles_an_empty_rescan_dataset_without_erroring()
    {
        // Reproduces the prod crash: importPage hard-indexes $dataset[0] on an
        // empty dataset -> "Undefined array key 0". Should degrade gracefully.
        [$url, $scan, $page] = $this->rescanImportUrl([]);

        $this->getJson($url)->assertStatus(422);

        // Nothing should have changed on the page.
        $this->assertDatabaseHas('pages', [
            'id' => $page->id,
            'rescan_id' => $page->rescan_id,
        ]);
    }

    /** @test */
    public function import_requires_authentication()
    {
        // These GET routes mutate (create pages / update scan counts), so a
        // logged-out request must be rejected before any work happens.
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $scan = Scan::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
        ]);

        // No acting user.
        $this->getJson("/api/{$organization->slug}/scans/{$scan->id}/import")
            ->assertUnauthorized();

        $this->assertDatabaseCount('pages', 0);
    }

    /** @test */
    public function import_page_requires_authentication()
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $scan = Scan::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
        ]);
        $rescan = Scan::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'is_single_page' => true,
        ]);
        $page = Page::factory()->create([
            'scan_id' => $scan->id,
            'rescan_id' => $rescan->id,
        ]);

        $this->getJson("/api/{$organization->slug}/sites/{$site->id}/scans/{$scan->id}/page/{$page->id}/rescan/import")
            ->assertUnauthorized();

        // The page's rescan link is untouched.
        $this->assertDatabaseHas('pages', ['id' => $page->id, 'rescan_id' => $rescan->id]);
    }
}
