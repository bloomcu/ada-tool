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
        //
        Schema::table('scans', function (Blueprint $table) {
            $table->dropForeign(['site_id']);
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::table('scans', function (Blueprint $table) {
            $table->dropForeign(['site_id']);
            $table->foreign('site_id')->references('id')->on('sites');
        });
    }
};
