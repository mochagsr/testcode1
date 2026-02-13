<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->index('created_at', 'audit_logs_created_at_idx');
            $table->index(['action', 'created_at'], 'audit_logs_action_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex('audit_logs_created_at_idx');
            $table->dropIndex('audit_logs_action_created_at_idx');
        });
    }
};
