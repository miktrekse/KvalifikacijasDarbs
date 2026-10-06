<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every scorer on a card keeps their own copy of each player's hole (PDGA Live
     * style), so shot numbers are unique per scorer. The new index is added before
     * the old one is dropped so the hole foreign key always has an index to use.
     */
    public function up(): void
    {
        Schema::table('competition_shots', function (Blueprint $table) {
            $table->unique(['competition_hole_id', 'user_id', 'recorded_by', 'shot_number'], 'competition_shots_hole_user_scorer_shot_unique');
        });
        Schema::table('competition_shots', function (Blueprint $table) {
            $table->dropUnique('competition_shots_hole_user_shot_unique');
        });
    }

    public function down(): void
    {
        Schema::table('competition_shots', function (Blueprint $table) {
            $table->unique(['competition_hole_id', 'user_id', 'shot_number'], 'competition_shots_hole_user_shot_unique');
        });
        Schema::table('competition_shots', function (Blueprint $table) {
            $table->dropUnique('competition_shots_hole_user_scorer_shot_unique');
        });
    }
};
