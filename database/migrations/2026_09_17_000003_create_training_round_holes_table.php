<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_round_holes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_round_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('number');
            $table->unsignedTinyInteger('par')->default(3);
            $table->unsignedSmallInteger('distance_m')->default(100);
            $table->timestamps();
            $table->unique(['training_round_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_round_holes');
    }
};
