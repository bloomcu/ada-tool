<?php

namespace DDD\App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use DDD\Domain\Sites\Site;
use DDD\App\Services\Apify\ApifyInterface;
use DDD\App\Services\Scans\ScanScheduleService;

class RunScheduledScans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scans:run-scheduled {--dry-run : List sites that would be scanned without triggering Apify.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trigger scans for sites whose scheduled scan date has arrived.';

    /**
     * Execute the console command.
     */
    public function handle(ApifyInterface $apifyService, ScanScheduleService $scheduleService): int
    {
        $dueSites = Site::query()
            ->where('scan_schedule', 'quarterly')
            ->whereNotNull('next_scan_at')
            ->where('next_scan_at', '<=', now())
            ->get();

        if ($dueSites->isEmpty()) {
            $this->info('No sites are due for a scheduled scan.');

            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $dueSites->each(function (Site $site) {
                $this->line("Dry run: {$site->domain} (site #{$site->id}) would be scanned.");
            });

            return Command::SUCCESS;
        }

        $dueSites->each(function (Site $site) use ($apifyService, $scheduleService) {
            try {
                $this->info("Triggering scan for site {$site->domain} (site #{$site->id}).");

                $actor = $apifyService->runActor('https://' . $site->domain, true);

                $scan = $site->scans()->create([
                    'organization_id' => $site->organization_id,
                    'run_id'          => $actor['run_id'],
                    'queue_id'        => $actor['queue_id'],
                    'dataset_id'      => $actor['dataset_id'],
                ]);

                $site->forceFill([
                    'next_scan_at' => $scheduleService->calculateNextRun($site->scan_schedule),
                ])->save();

                $this->line("Scan {$scan->id} created for site {$site->domain}.");
            } catch (\Throwable $exception) {
                Log::error('Unable to trigger scheduled scan', [
                    'site_id' => $site->id,
                    'site_domain' => $site->domain,
                    'message' => $exception->getMessage(),
                ]);

                $this->error("Failed to trigger scan for site {$site->domain}: {$exception->getMessage()}");
            }
        });

        return Command::SUCCESS;
    }
}
