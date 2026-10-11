<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Round ratings stop at 0 (Course::MIN_RATING), like the player ratings averaged from
     * them (rating_before, rating_after, users.rating), so the column is unsigned too.
     */
    public function up(): void
    {
        // Rounds rated before the floor existed; the next ratings:recalculate rebuilds them anyway
        DB::table('round_ratings')->where('round_rating', '<', 0)->update(['round_rating' => 0]);

        Schema::table('round_ratings', function (Blueprint $table) {
            $table->unsignedSmallInteger('round_rating')->change();
        });
    }

    public function down(): void
    {
        Schema::table('round_ratings', function (Blueprint $table) {
            $table->smallInteger('round_rating')->change();
        });
    }
};
