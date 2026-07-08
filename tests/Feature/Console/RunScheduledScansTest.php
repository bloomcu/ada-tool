<?php

namespace Tests\Feature\Console;

use Tests\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Sites\Site;
use DDD\Domain\Scans\Mail\ScheduledScanStarted;
use DDD\App\Services\Apify\ApifyInterface;

class RunScheduledScansTest extends TestCase
{
    private function fakeApifyRun(): void
    {
        Http::fake([
            'api.apify.com/*' => Http::response([
                'data' => [
                    'id' => 'sched-run',
                    'defaultRequestQueueId' => 'sched-queue',
                    'defaultDatasetId' => 'sched-dataset',
                ],
            ]),
        ]);
    }

    private function dueSite(Organization $organization, array $overrides = []): Site
    {
        return Site::factory()->create(array_merge([
            'organization_id' => $organization->id,
            'scan_schedule' => 'quarterly',
            'next_scan_at' => now()->subDay(), // due
        ], $overrides));
    }

    /** @test */
    public function it_scans_due_sites_advances_the_schedule_and_notifies()
    {
        $this->fakeApifyRun();
        Mail::fake();

        $organization = Organization::factory()->create();
        $site = $this->dueSite($organization, [
            'scan_notification_emails' => 'alice@example.com, bob@example.com',
        ]);

        $this->artisan('scans:run-scheduled')->assertExitCode(0);

        // A scan was created from the Apify run.
        $this->assertDatabaseHas('scans', [
            'site_id' => $site->id,
            'organization_id' => $organization->id,
            'run_id' => 'sched-run',
            'dataset_id' => 'sched-dataset',
        ]);

        // next_scan_at was rolled forward into the future (stored as a string).
        $this->assertTrue(Carbon::parse($site->refresh()->next_scan_at)->isFuture());

        // One notification per recipient.
        Mail::assertSent(ScheduledScanStarted::class, 2);
    }

    /** @test */
    public function it_ignores_sites_that_are_not_due()
    {
        $this->fakeApifyRun();
        $organization = Organization::factory()->create();

        // Manual schedule, future run, and null run — none are due.
        Site::factory()->create(['organization_id' => $organization->id, 'scan_schedule' => 'manual', 'next_scan_at' => now()->subDay()]);
        Site::factory()->create(['organization_id' => $organization->id, 'scan_schedule' => 'quarterly', 'next_scan_at' => now()->addMonth()]);
        Site::factory()->create(['organization_id' => $organization->id, 'scan_schedule' => 'quarterly', 'next_scan_at' => null]);

        $this->artisan('scans:run-scheduled')
            ->expectsOutput('No sites are due for a scheduled scan.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('scans', 0);
        Http::assertNothingSent();
    }

    /** @test */
    public function dry_run_lists_due_sites_without_side_effects()
    {
        $this->fakeApifyRun();
        Mail::fake();

        $organization = Organization::factory()->create();
        $site = $this->dueSite($organization, ['scan_notification_emails' => 'alice@example.com']);
        $originalNextScan = $site->refresh()->next_scan_at; // string as stored

        $this->artisan('scans:run-scheduled', ['--dry-run' => true])->assertExitCode(0);

        // No scan, no Apify call, no mail, and the schedule is untouched.
        $this->assertDatabaseCount('scans', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
        $this->assertEquals($originalNextScan, $site->refresh()->next_scan_at);
    }

    /** @test */
    public function an_apify_failure_for_one_site_is_contained_and_does_not_halt_the_command()
    {
        Mail::fake();

        // Force the Apify call to blow up; the command should catch, log, and
        // keep going (return SUCCESS) rather than crash the whole batch.
        $this->mock(ApifyInterface::class, function ($mock) {
            $mock->shouldReceive('runActor')->andThrow(new \Exception('Apify unavailable'));
        });

        $organization = Organization::factory()->create();
        $site = $this->dueSite($organization);

        $this->artisan('scans:run-scheduled')->assertExitCode(0);

        // Nothing persisted, schedule not advanced (so it retries next run), no mail.
        $this->assertDatabaseCount('scans', 0);
        $this->assertTrue(Carbon::parse($site->refresh()->next_scan_at)->isPast());
        Mail::assertNothingSent();
    }
}
