<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'verified', 'user', 'guest') NOT NULL DEFAULT 'user'");
        }
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'guest')->delete();

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'verified', 'user') NOT NULL DEFAULT 'user'");
        }
    }
};
