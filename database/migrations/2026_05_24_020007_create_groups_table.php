<?php

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
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Stage::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();

            $table->unique(['stage_id', 'name']);
            $table->index(['stage_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
