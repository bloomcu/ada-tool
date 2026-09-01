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
        {--chunk=500 : How many ids to stream per batch (ids only — results are read one page at a time)}';

    /**
     * @var string
     */
    protected $description = "Backfill pages.customer_reviewable from stored results (resumable).";

    public function handle(): int
    {
        $query = Page::query()
            ->whereNull('customer_reviewable')
            ->when($this->option('scan'), fn ($q, $scanId) => $q->where('scan_id', $scanId));

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Nothing to backfill (all targeted pages already set).');

            return self::SUCCESS;
        }

        $this->info("Backfilling {$total} page(s)…");
        $bar = $this->output->createProgressBar($total);

        // Stream ID-ONLY rows (tiny) and read each page's large `results` blob ONE AT A TIME.
        // Peak memory is a single page's results — never a whole chunk of blobs (which is what
        // OOM'd a full-blob chunkById on big scans).
        $query->select('id')->lazyById((int) $this->option('chunk'))->each(function ($page) use ($bar) {
            $raw = DB::table('pages')->where('id', $page->id)->value('results');
            $results = json_decode($raw ?? '', true);

            DB::table('pages')->where('id', $page->id)->update([
                'customer_reviewable' => is_array($results) ? CustomerEditableRules::reviewable($results) : false,
            ]);

            unset($raw, $results);
            $bar->advance();
        });

        $bar->finish();
        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }
}
