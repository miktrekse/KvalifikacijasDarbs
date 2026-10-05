<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One rated tournament round per player: the tournament log on profiles
        Schema::create('round_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('strokes');
            $table->unsignedSmallInteger('par');
            $table->smallInteger('round_rating');
            $table->unsignedSmallInteger('rating_before')->nullable();
            $table->unsignedSmallInteger('rating_after')->nullable();
            $table->smallInteger('rating_change')->nullable();
            $table->unsignedSmallInteger('rounds_counted')->default(0);
            $table->boolean('is_propagator')->default(false);
            $table->timestamp('played_at');
            $table->timestamps();
            $table->unique(['competition_id', 'user_id']);
            $table->index(['user_id', 'played_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('round_ratings');
    }
};
