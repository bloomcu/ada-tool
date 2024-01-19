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
            
            $table->integer('violation_count')->nullable();
            $table->integer('warning_count')->nullable();
            
            $table->integer('violation_count_pages')->nullable();
            $table->integer('warning_count_pages')->nullable();

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
            
            $table->dropColumn('violation_count');
            $table->dropColumn('warning_count');
        
            $table->dropColumn('violation_count_pages');
            $table->dropColumn('warning_count_pages');
        });
    }
};
