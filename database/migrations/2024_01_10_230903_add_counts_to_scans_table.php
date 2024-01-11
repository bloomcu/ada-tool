<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->integer('pass_count')->nullable();
            $table->integer('fail_count')->nullable();
            $table->integer('warning_count')->nullable();
            $table->integer('manual_count')->nullable();
            $table->integer('hidden_count')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('scans', function (Blueprint $table) {
            //
            $table->dropColumn('pass_count');
            $table->dropColumn('fail_count');
            $table->dropColumn('warning_count');
            $table->dropColumn('manual_count');
            $table->dropColumn('hidden_count');
        });
    }
};
