<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('event_date');
            $table->string('location')->nullable();
            $table->string('course_name')->nullable();
            $table->string('format')->default('stroke_play');
            $table->string('divisions')->nullable();
            $table->integer('holes')->default(18);
            $table->decimal('entry_fee', 10, 2)->default(0);
            $table->string('currency')->default('EUR');
            $table->integer('max_participants')->nullable();
            $table->string('registration_link')->nullable();
            $table->timestamp('registration_deadline')->nullable();
            $table->enum('status', ['upcoming', 'ongoing', 'completed', 'cancelled'])->default('upcoming');
            $table->boolean('is_approved')->default(false);
            $table->boolean('is_public')->default(true);
            $table->text('results_link')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitions');
    }
};
