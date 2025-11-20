<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ---- helpers -------------------------------------------------------
        $hasFk = function (string $name): bool {
            return (int) (DB::selectOne("
                SELECT COUNT(*) c
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'pages'
                  AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                  AND CONSTRAINT_NAME = ?
            ", [$name])->c ?? 0) > 0;
        };

        $hasCol = fn (string $col) => Schema::hasColumn('pages', $col);

        // ---- part 1: scan_id FK (drop if exists, then (re)add) -------------
        if ($hasFk('pages_scan_id_foreign')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->dropForeign(['scan_id']); // drops pages_scan_id_foreign
            });
        }
        // (re)add using Laravel default name
        if (!$hasFk('pages_scan_id_foreign')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->foreign('scan_id')
                      ->references('id')->on('scans')
                      ->onDelete('cascade');
            });
        }

        // ---- part 2: rescan_id copy -> drop -> rename -> add FK ------------

        // 2a) Create new_rescan_id if missing
        if (!$hasCol('new_rescan_id')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->unsignedBigInteger('new_rescan_id')->nullable();
            });
        }

        // 2b) Copy data only if both columns exist and new_rescan_id is empty
        if ($hasCol('rescan_id') && $hasCol('new_rescan_id')) {
            // copy once; harmless to rerun
            DB::statement('UPDATE pages SET new_rescan_id = rescan_id WHERE new_rescan_id IS NULL AND rescan_id IS NOT NULL');
        }

        // 2c) Drop old rescan_id if it still exists
        if ($hasCol('rescan_id')) {
            // Drop FK first if it exists under the default name
            if ($hasFk('pages_rescan_id_foreign')) {
                Schema::table('pages', function (Blueprint $table) {
                    $table->dropForeign(['rescan_id']);
                });
            }
            Schema::table('pages', function (Blueprint $table) {
                $table->dropColumn('rescan_id');
            });
        }

        // 2d) Rename new_rescan_id -> rescan_id if needed
        if ($hasCol('new_rescan_id') && !$hasCol('rescan_id')) {
            Schema::table('pages', function (Blueprint $table) {
                // requires doctrine/dbal
                $table->renameColumn('new_rescan_id', 'rescan_id');
            });
        }

        // 2e) Add FK on rescan_id (Laravel default name)
        if ($hasCol('rescan_id') && !$hasFk('pages_rescan_id_foreign')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->foreign('rescan_id')
                      ->references('id')->on('scans')
                      ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        // ---- helpers -------------------------------------------------------
        $hasFk = function (string $name): bool {
            return (int) (DB::selectOne("
                SELECT COUNT(*) c
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'pages'
                  AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                  AND CONSTRAINT_NAME = ?
            ", [$name])->c ?? 0) > 0;
        };
        $hasCol = fn (string $col) => Schema::hasColumn('pages', $col);

        // revert scan_id FK to non-cascade (match your original down)
        if ($hasFk('pages_scan_id_foreign')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->dropForeign(['scan_id']);
            });
        }
        Schema::table('pages', function (Blueprint $table) {
            // add without onDelete cascade (Laravel default name)
            $table->foreign('scan_id')->references('id')->on('scans');
        });

        // rescan_id: drop FK if present
        if ($hasFk('pages_rescan_id_foreign')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->dropForeign(['rescan_id']);
            });
        }

        // create new_rescan_id (signed) if missing
        if (!$hasCol('new_rescan_id')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->bigInteger('new_rescan_id')->nullable();
            });
        }

        // copy back if both columns exist
        if ($hasCol('rescan_id') && $hasCol('new_rescan_id')) {
            DB::statement('UPDATE pages SET new_rescan_id = rescan_id WHERE new_rescan_id IS NULL AND rescan_id IS NOT NULL');
        }

        // drop unsigned rescan_id if exists
        if ($hasCol('rescan_id')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->dropColumn('rescan_id');
            });
        }

        // rename new_rescan_id -> rescan_id if needed
        if ($hasCol('new_rescan_id') && !$hasCol('rescan_id')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->renameColumn('new_rescan_id', 'rescan_id'); // needs doctrine/dbal
            });
        }
    }
};
