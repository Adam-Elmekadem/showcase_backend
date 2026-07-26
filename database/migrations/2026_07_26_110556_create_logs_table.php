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
        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('film_id')->constrained()->cascadeOnDelete();
            $table->date('watched_on')->nullable();
            $table->boolean('is_rewatch')->default(false);
            $table->decimal('rating_overall', 2, 1)->nullable();
            $table->decimal('rating_story', 2, 1)->nullable();
            $table->decimal('rating_direction', 2, 1)->nullable();
            $table->decimal('rating_acting', 2, 1)->nullable();
            $table->decimal('rating_cinematography', 2, 1)->nullable();
            $table->decimal('rating_music', 2, 1)->nullable();
            $table->text('review')->nullable();
            $table->boolean('contains_spoilers')->default(false);
            $table->unsignedInteger('likes_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};
