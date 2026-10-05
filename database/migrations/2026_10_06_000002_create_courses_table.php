<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A rated course layout: what a par round is worth and how much each stroke changes it
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_key');
            $table->unsignedTinyInteger('holes');
            $table->unsignedSmallInteger('par');
            $table->decimal('par_rating', 6, 1)->default(950);
            $table->decimal('points_per_stroke', 4, 2)->default(7);
            $table->unsignedInteger('rated_rounds')->default(0);
            $table->unsignedInteger('rated_events')->default(0);
            $table->timestamps();
            $table->unique(['name_key', 'holes', 'par']);
        });

        Schema::table('competitions', function (Blueprint $table) {
            $table->foreignId('course_id')->nullable()->after('course_name')->constrained()->nullOnDelete();
            $table->decimal('course_rating_before', 6, 1)->nullable()->after('groups_assigned_at');
            $table->decimal('course_rating_after', 6, 1)->nullable()->after('course_rating_before');
            $table->timestamp('rated_at')->nullable()->after('course_rating_after');
        });
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('course_id');
            $table->dropColumn(['course_rating_before', 'course_rating_after', 'rated_at']);
        });

        Schema::dropIfExists('courses');
    }
};
