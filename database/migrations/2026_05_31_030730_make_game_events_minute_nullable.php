<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('game_events', function (Blueprint $table) {
            // Quick-entry events (pre-kickoff commentary, unknown minute) can
            // be recorded without a minute, matching the form's nullable rule.
            $table->unsignedSmallInteger('minute')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_events', function (Blueprint $table) {
            $table->unsignedSmallInteger('minute')->nullable(false)->change();
        });
    }
};
