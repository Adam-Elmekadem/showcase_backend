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
        Schema::table('suggestions', function (Blueprint $table) {
            $table->foreignId('film_id')->nullable()->change();
            $table->foreignId('film_list_id')->nullable()->after('film_id')->constrained('film_lists')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suggestions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('film_list_id');
            $table->foreignId('film_id')->nullable(false)->change();
        });
    }
};
