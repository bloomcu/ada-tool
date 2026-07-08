<?php

namespace Tests\Feature\Pages;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use DDD\Domain\Base\Users\User;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Sites\Site;
use DDD\Domain\Scans\Scan;
use DDD\Domain\Pages\Page;

class PageScanControllerTest extends TestCase
{
    private function fakeApifyRun(): void
    {
        Http::fake([
            'api.apify.com/*' => Http::response([
                'data' => [
                    'id' => 'rescan-run',
                    'defaultRequestQueueId' => 'rescan-queue',
                    'defaultDatasetId' => 'rescan-dataset',
                ],
            ]),
        ]);
    }

    /**
     * Build a full org → site → scan → page chain (the route scope-binds them
     * in that order). The page needs an `eval_url` in its results for a rescan.
     */
    private function chain(bool $withEvalUrl = true): array
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->create([
            'organization_id' => $organization->id,
            'include_3pi' => true,
        ]);
        $scan = Scan::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
        ]);
        $page = Page::factory()->create([
            'scan_id' => $scan->id,
            'results' => json_encode([
                'eval_url' => $withEvalUrl ? 'https://example.com/contact' : null,
                'rule_results' => [],
            ]),
        ]);

        return [$organization, $site, $scan, $page];
    }

    private function rescanUrl(Organization $o, Site $s, Scan $sc, Page $p): string
    {
        return "/api/{$o->slug}/sites/{$s->id}/scans/{$sc->id}/page/{$p->id}/scan";
    }

    /** @test */
    public function it_rescans_a_single_page_and_links_the_new_scan_to_the_page()
    {
        $this->fakeApifyRun();
        [$org, $site, $scan, $page] = $this->chain();

        Sanctum::actingAs(User::factory()->create(['organization_id' => $org->id]));

        $response = $this->postJson($this->rescanUrl($org, $site, $scan, $page));

        $response->assertCreated()
            ->assertJsonPath('data.run_id', 'rescan-run')
            ->assertJsonPath('data.dataset_id', 'rescan-dataset');

        // A new single-page scan is created for the site.
        $this->assertDatabaseHas('scans', [
            'site_id' => $site->id,
            'organization_id' => $org->id,
            'run_id' => 'rescan-run',
            'is_single_page' => true,
        ]);

        // The page is linked to the new rescan.
        $newScanId = $response->json('data.id');
        $this->assertDatabaseHas('pages', [
            'id' => $page->id,
            'rescan_id' => $newScanId,
        ]);
    }

    /** @test */
    public function it_runs_the_actor_for_the_pages_eval_url_without_enqueuing_links()
    {
        $this->fakeApifyRun();
        [$org, $site, $scan, $page] = $this->chain();

        Sanctum::actingAs(User::factory()->create(['organization_id' => $org->id]));

        $this->postJson($this->rescanUrl($org, $site, $scan, $page))->assertCreated();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.apify.com/v2/acts')
                && $request['shouldEnqueueLinks'] === false
                && $request['startUrls'] === [['url' => 'https://example.com/contact/']];
        });
    }

    /** @test */
    public function it_requires_authentication()
    {
        [$org, $site, $scan, $page] = $this->chain();

        $this->postJson($this->rescanUrl($org, $site, $scan, $page))
            ->assertUnauthorized();
    }
}
