<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_round_shots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_round_hole_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('shot_number');
            $table->string('result', 20);
            $table->unsignedTinyInteger('strokes')->default(1);
            $table->timestamps();
            $table->unique(['training_round_hole_id', 'user_id', 'shot_number'], 'training_round_shots_hole_user_shot_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_round_shots');
    }
};
