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
        Schema::table('logs', function (Blueprint $table) {
            // A fixed, single-select tag for standalone quote logs (funny,
            // romantic, philosophical, ...). Nullable since ordinary reviews
            // (and quotes attached to a full review) don't require one.
            $table->string('quote_category')->nullable()->after('quote');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            $table->dropColumn('quote_category');
        });
    }
};
