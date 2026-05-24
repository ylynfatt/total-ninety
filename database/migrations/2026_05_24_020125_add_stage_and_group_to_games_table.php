<?php

use App\Models\Group;
use App\Models\Stage;
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
        Schema::table('games', function (Blueprint $table) {
            $table->foreignIdFor(Stage::class)
                ->nullable()
                ->after('season_id')
                ->constrained()
                ->cascadeOnDelete();

            // Nullable: only set for games inside a group/conference stage.
            $table->foreignIdFor(Group::class)
                ->nullable()
                ->after('stage_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropConstrainedForeignIdFor(Group::class);
            $table->dropConstrainedForeignIdFor(Stage::class);
        });
    }
};
