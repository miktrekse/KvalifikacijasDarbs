<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * score_only: the hole was scored as a plain number (UDisc-style) for a player
     * nobody tracked shot by shot. Stored as one in_basket row carrying all the
     * strokes, so totals still add up while shot stats skip the hole.
     */
    public function up(): void
    {
        foreach (['competition_shots', 'training_round_shots'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->boolean('score_only')->default(false)->after('distance_m');
            });
        }
    }

    public function down(): void
    {
        foreach (['competition_shots', 'training_round_shots'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('score_only');
            });
        }
    }
};
