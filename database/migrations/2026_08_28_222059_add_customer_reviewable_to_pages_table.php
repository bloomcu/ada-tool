<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Disposable first-pass "review this first" flag (see CustomerEditableRules).
 * Derived from `page.results` and kept in sync going forward wherever the counts are
 * written (ScanImportController import/importPage). Column only — NO backfill here:
 * decoding every page's large results blob inline would make the deploy migration a
 * memory/time hazard. Existing pages stay NULL until re-scanned, or backfill them
 * explicitly (resumable, chunked) via `php artisan pages:backfill-reviewable`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->boolean('customer_reviewable')->nullable()->after('warning_count');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('customer_reviewable');
        });
    }
};
