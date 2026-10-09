<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A tournament keeps the course it was created for: where it is and its exact hole
     * layout (number, par, distance) as picked on the map. The holes drawn 30 minutes before
     * the start, and every rating calculated from them, use this snapshot instead of a guess
     * from the course name.
     */
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->decimal('course_lat', 10, 7)->nullable()->after('course_name');
            $table->decimal('course_lon', 10, 7)->nullable()->after('course_lat');
            $table->json('course_layout')->nullable()->after('course_lon');
        });
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn(['course_lat', 'course_lon', 'course_layout']);
        });
    }
};
