<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the running match clock was last (re)started. Null whenever the
     * clock is paused or stopped; the shown minute then equals current_minute.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->timestamp('clock_started_at')->nullable()->after('current_minute');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('clock_started_at');
        });
    }
};
