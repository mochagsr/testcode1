<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->index('name', 'users_name_idx');
            $table->index(['role', 'finance_locked', 'name'], 'users_role_finance_name_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_name_idx');
            $table->dropIndex('users_role_finance_name_idx');
        });
    }
};
