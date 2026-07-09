<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sanctum 2 -> 3 added an `expires_at` column to personal_access_tokens.
 * Sanctum's migration is loaded from the package, so databases created under
 * Sanctum 2 already have the create migration recorded and will NOT pick up
 * the new column on `migrate`. This backfills it.
 *
 * Guarded with hasColumn so it's a no-op on fresh databases where the Sanctum 3
 * package migration already created the column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('personal_access_tokens', 'expires_at')) {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                $table->timestamp('expires_at')->nullable()->after('abilities');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('personal_access_tokens', 'expires_at')) {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                $table->dropColumn('expires_at');
            });
        }
    }
};
