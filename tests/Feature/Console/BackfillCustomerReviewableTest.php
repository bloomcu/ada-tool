<?php

namespace Tests\Feature\Console;

use Tests\TestCase;
use DDD\Domain\Scans\Scan;
use DDD\Domain\Pages\Page;

class BackfillCustomerReviewableTest extends TestCase
{
    /** @test */
    public function it_backfills_null_pages_from_their_results()
    {
        $scan = Scan::factory()->create();

        $editable = Page::factory()->create([
            'scan_id' => $scan->id,
            'customer_reviewable' => null,
            'results' => json_encode(['rule_results' => [
                ['rule_id' => 'HEADING_5', 'elements_violation' => 0, 'elements_warning' => 1],
            ]]),
        ]);
        $structural = Page::factory()->create([
            'scan_id' => $scan->id,
            'customer_reviewable' => null,
            'results' => json_encode(['rule_results' => [
                ['rule_id' => 'WIDGET_3', 'elements_violation' => 2, 'elements_warning' => 0],
            ]]),
        ]);

        $this->artisan('pages:backfill-reviewable')->assertExitCode(0);

        $this->assertDatabaseHas('pages', ['id' => $editable->id, 'customer_reviewable' => true]);
        $this->assertDatabaseHas('pages', ['id' => $structural->id, 'customer_reviewable' => false]);
    }

    /** @test */
    public function it_skips_already_set_rows_and_can_target_one_scan()
    {
        $scanA = Scan::factory()->create();
        $scanB = Scan::factory()->create();

        // Already set to false — must stay false even though it has a customer-editable rule.
        $alreadySet = Page::factory()->create([
            'scan_id' => $scanA->id,
            'customer_reviewable' => false,
            'results' => json_encode(['rule_results' => [
                ['rule_id' => 'HEADING_5', 'elements_violation' => 1, 'elements_warning' => 0],
            ]]),
        ]);
        // NULL, but in a different scan — must be untouched when we target scanA only.
        $otherScan = Page::factory()->create([
            'scan_id' => $scanB->id,
            'customer_reviewable' => null,
            'results' => json_encode(['rule_results' => [
                ['rule_id' => 'HEADING_5', 'elements_violation' => 1, 'elements_warning' => 0],
            ]]),
        ]);

        $this->artisan('pages:backfill-reviewable', ['--scan' => $scanA->id])->assertExitCode(0);

        $this->assertDatabaseHas('pages', ['id' => $alreadySet->id, 'customer_reviewable' => false]);
        $this->assertDatabaseHas('pages', ['id' => $otherScan->id, 'customer_reviewable' => null]);
    }
}
