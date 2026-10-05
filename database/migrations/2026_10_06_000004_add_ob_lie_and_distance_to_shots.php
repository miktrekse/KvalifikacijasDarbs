<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ob_lie: where play resumes after an OB throw (circle_1, circle_2, fairway, off_fairway, tee, drop_zone).
     * distance_m: how far a made throw from outside Circle 2 travelled.
     */
    public function up(): void
    {
        foreach (['competition_shots', 'training_round_shots'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('ob_lie', 20)->nullable()->after('result');
                $table->unsignedSmallInteger('distance_m')->nullable()->after('strokes');
            });
        }
    }

    public function down(): void
    {
        foreach (['competition_shots', 'training_round_shots'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['ob_lie', 'distance_m']);
            });
        }
    }
};
