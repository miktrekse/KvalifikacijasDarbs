<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->allowRoles(['admin', 'verified', 'user']);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'verified')->update(['role' => 'user']);
        $this->allowRoles(['admin', 'user']);
    }

    /**
     * MySQL changes the enum in place; other databases (SQLite in the test suite) get the
     * column rebuilt by the schema builder, which also replaces SQLite's CHECK constraint.
     */
    private function allowRoles(array $roles): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $list = implode(', ', array_map(fn (string $role) => "'{$role}'", $roles));
            DB::statement("ALTER TABLE users MODIFY role ENUM({$list}) NOT NULL DEFAULT 'user'");

            return;
        }

        Schema::table('users', function (Blueprint $table) use ($roles) {
            $table->enum('role', $roles)->default('user')->change();
        });
    }
};
