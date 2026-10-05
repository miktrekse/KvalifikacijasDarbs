<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->unsignedTinyInteger('starting_hole')->default(1);
            $table->timestamps();
            $table->unique(['competition_id', 'number']);
        });

        Schema::table('competition_registrations', function (Blueprint $table) {
            $table->foreignId('competition_group_id')->nullable()->after('user_id')
                ->constrained('competition_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('competition_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('competition_group_id');
        });

        Schema::dropIfExists('competition_groups');
    }
};
