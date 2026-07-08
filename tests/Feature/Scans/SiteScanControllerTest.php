<?php

namespace Tests\Feature\Scans;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use DDD\Domain\Base\Users\User;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Sites\Site;

class SiteScanControllerTest extends TestCase
{
    /**
     * Fake the Apify "run actor" endpoint with a successful run payload.
     */
    private function fakeApifyRun(): void
    {
        Http::fake([
            'api.apify.com/*' => Http::response([
                'data' => [
                    'id' => 'run-123',
                    'defaultRequestQueueId' => 'queue-123',
                    'defaultDatasetId' => 'dataset-123',
                ],
            ]),
        ]);
    }

    /** @test */
    public function it_triggers_an_apify_scan_for_a_site_and_persists_the_run()
    {
        $this->fakeApifyRun();

        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $user = User::factory()->create(['organization_id' => $organization->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/{$organization->slug}/sites/{$site->id}/scan");

        $response->assertCreated()
            ->assertJsonPath('data.run_id', 'run-123')
            ->assertJsonPath('data.queue_id', 'queue-123')
            ->assertJsonPath('data.dataset_id', 'dataset-123');

        // Exactly one scan row is created (guards against the double-create
        // regression where two identical rows were persisted per site scan).
        $this->assertDatabaseCount('scans', 1);

        // Note: the response omits `status` (the controller returns the
        // unrefreshed model, so the DB default isn't loaded yet) — but the
        // persisted row carries the default 'READY'.
        $this->assertDatabaseHas('scans', [
            'site_id' => $site->id,
            'organization_id' => $organization->id,
            'run_id' => 'run-123',
            'queue_id' => 'queue-123',
            'dataset_id' => 'dataset-123',
            'status' => 'READY',
        ]);
    }

    /** @test */
    public function it_forwards_the_sites_3pi_flag_to_apify()
    {
        $this->fakeApifyRun();

        $organization = Organization::factory()->create();
        $site = Site::factory()->create([
            'organization_id' => $organization->id,
            'include_3pi' => false,
        ]);
        $user = User::factory()->create(['organization_id' => $organization->id]);

        Sanctum::actingAs($user);

        $this->postJson("/api/{$organization->slug}/sites/{$site->id}/scan")->assertCreated();

        // runActor sends ignoreKnown3pi as the NEGATION of the site's include_3pi
        // (see ApifyADAScanner: 'ignoreKnown3pi' => !$include3pi). So a site with
        // include_3pi = false must tell Apify to ignore known 3pi (true), and links
        // are always enqueued for a full site scan.
        Http::assertSent(function ($request) use ($site) {
            return str_contains($request->url(), 'api.apify.com/v2/acts')
                && $request['ignoreKnown3pi'] === true
                && $request['shouldEnqueueLinks'] === true
                && $request['startUrls'] === [['url' => 'https://' . $site->domain . '/']];
        });
    }

    /** @test */
    public function it_requires_authentication()
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);

        $this->postJson("/api/{$organization->slug}/sites/{$site->id}/scan")
            ->assertUnauthorized();
    }
}
