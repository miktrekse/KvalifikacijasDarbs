<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_holes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('number');
            $table->unsignedTinyInteger('par')->default(3);
            $table->unsignedSmallInteger('distance_m')->nullable();
            $table->timestamps();
            $table->unique(['competition_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_holes');
    }
};
