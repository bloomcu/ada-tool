<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        // First handle the scan_id foreign key
        Schema::table('pages', function (Blueprint $table) {
            $table->dropForeign(['scan_id']);
            $table->foreign('scan_id')->references('id')->on('scans')->onDelete('cascade');
        });

        // Now safely handle the rescan_id conversion
        Schema::table('pages', function (Blueprint $table) {
            // Create new column with correct type
            $table->unsignedBigInteger('new_rescan_id')->nullable();
        });

        // Copy data from old column to new column
        DB::statement('UPDATE pages SET new_rescan_id = rescan_id');

        Schema::table('pages', function (Blueprint $table) {
            // Drop old column
            $table->dropColumn('rescan_id');
        });

        Schema::table('pages', function (Blueprint $table) {
            // Rename new column to original name
            $table->renameColumn('new_rescan_id', 'rescan_id');
            // Add foreign key constraint
            $table->foreign('rescan_id')->references('id')->on('scans')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // First revert the scan_id changes
        Schema::table('pages', function (Blueprint $table) {
            $table->dropForeign(['scan_id']);
            $table->foreign('scan_id')->references('id')->on('scans');
        });

        // Now revert the rescan_id changes
        Schema::table('pages', function (Blueprint $table) {
            // Drop the foreign key
            $table->dropForeign(['rescan_id']);
        });

        Schema::table('pages', function (Blueprint $table) {
            // Create new column with original type
            $table->bigInteger('new_rescan_id')->nullable();
        });

        // Copy data back
        DB::statement('UPDATE pages SET new_rescan_id = rescan_id');

        Schema::table('pages', function (Blueprint $table) {
            // Drop the unsigned column
            $table->dropColumn('rescan_id');
        });

        Schema::table('pages', function (Blueprint $table) {
            // Rename back to original name
            $table->renameColumn('new_rescan_id', 'rescan_id');
        });
    }
};
