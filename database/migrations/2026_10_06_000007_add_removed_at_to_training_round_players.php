<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * removed_at: the player deleted the round from their own list. The round stays
     * for everyone else on it, and is deleted for good once every player has removed it.
     */
    public function up(): void
    {
        Schema::table('training_round_players', function (Blueprint $table) {
            $table->timestamp('removed_at')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('training_round_players', function (Blueprint $table) {
            $table->dropColumn('removed_at');
        });
    }
};
