<?php

namespace DDD\App\Console\Commands;

use DDD\Domain\Pages\Page;
use DDD\Domain\Pages\CustomerEditableRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfills pages.customer_reviewable from stored results — kept OUT of the migration
 * because decoding every page's large results blob is heavy. Resumable (only touches
 * rows still NULL, so re-running or interrupting is safe) and memory-bounded (chunked,
 * selects just id + results). Writes via the query builder to avoid bumping timestamps.
 */
class BackfillCustomerReviewable extends Command
{
    /**
     * @var string
     */
    protected $signature = 'pages:backfill-reviewable
        {--scan= : Limit to a single scan id}
        {--chunk=200 : Rows per DB round-trip}';

    /**
     * @var string
     */
    protected $description = "Backfill pages.customer_reviewable from stored results (resumable, chunked).";

    public function handle(): int
    {
        $query = Page::query()
            ->whereNull('customer_reviewable')
            ->when($this->option('scan'), fn ($q, $scanId) => $q->where('scan_id', $scanId))
            ->select('id', 'results');

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Nothing to backfill (all targeted pages already set).');

            return self::SUCCESS;
        }

        $this->info("Backfilling {$total} page(s)…");
        $bar = $this->output->createProgressBar($total);

        $query->chunkById((int) $this->option('chunk'), function ($pages) use ($bar) {
            foreach ($pages as $page) {
                $results = is_array($page->results) ? $page->results : [];
                DB::table('pages')->where('id', $page->id)->update([
                    'customer_reviewable' => CustomerEditableRules::reviewable($results),
                ]);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }
}
