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
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('period', ['daily', 'weekly', 'monthly', 'yearly']);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            // Flat criteria columns rather than a JSON blob -- authored
            // directly via Tinker/seeders (no admin UI yet), so keeping the
            // shape simple to hand-write matters more than generality.
            $table->string('criteria_type'); // 'watch_count' | 'genre_watch_count'
            $table->unsignedInteger('criteria_target');
            $table->string('criteria_genre')->nullable(); // only used by genre_watch_count
            $table->timestamps();

            $table->index(['period', 'starts_at', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};
