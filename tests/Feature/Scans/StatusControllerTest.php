<?php

namespace Tests\Feature\Scans;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Sites\Site;
use DDD\Domain\Scans\Scan;

class StatusControllerTest extends TestCase
{
    /**
     * Fake the two Apify endpoints getStatus() hits: the actor-run and the
     * request-queue.
     */
    private function fakeApifyStatus(string $status = 'RUNNING'): void
    {
        Http::fake([
            'api.apify.com/v2/actor-runs/*' => Http::response([
                'data' => ['status' => $status],
            ]),
            'api.apify.com/v2/request-queues/*' => Http::response([
                'data' => [
                    'totalRequestCount' => 10,
                    'handledRequestCount' => 4,
                    'pendingRequestCount' => 6,
                ],
            ]),
        ]);
    }

    /** @test */
    public function it_returns_the_apify_status_and_persists_it_on_the_scan()
    {
        $this->fakeApifyStatus('RUNNING');

        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $scan = Scan::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'status' => 'READY',
        ]);

        // The show route is public.
        $this->getJson("/api/{$organization->slug}/scans/{$scan->id}/status")
            ->assertOk()
            ->assertJsonPath('data', 'RUNNING');

        $this->assertDatabaseHas('scans', [
            'id' => $scan->id,
            'status' => 'RUNNING',
        ]);
    }

    /** @test */
    public function it_does_not_resolve_a_scan_from_another_organization()
    {
        $this->fakeApifyStatus();

        $organization = Organization::factory()->create();
        $other = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $other->id]);
        $scan = Scan::factory()->create([
            'organization_id' => $other->id,
            'site_id' => $site->id,
        ]);

        $this->getJson("/api/{$organization->slug}/scans/{$scan->id}/status")
            ->assertNotFound();
    }
}
