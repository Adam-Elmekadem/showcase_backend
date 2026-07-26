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
        Schema::create('film_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('film_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['director', 'writer', 'cinematographer', 'composer', 'actor'])->index();
            $table->string('character')->nullable();
            $table->unsignedSmallInteger('billing_order')->nullable();
            $table->timestamps();

            $table->unique(['film_id', 'person_id', 'role', 'character']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('film_credits');
    }
};
