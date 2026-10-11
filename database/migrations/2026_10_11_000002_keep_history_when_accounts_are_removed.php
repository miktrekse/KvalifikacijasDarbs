<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Removing an account no longer deletes sport history. The admin "delete" anonymises the
     * account (anonymized_at), and should a user row ever really be deleted, the events they
     * organised and the training rounds they started stay, just without an owner.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('anonymized_at')->nullable()->after('role');
        });

        foreach (['competitions', 'training_rounds'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->change();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['competitions', 'training_rounds'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('user_id')->nullable(false)->change();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('anonymized_at');
        });
    }
};
