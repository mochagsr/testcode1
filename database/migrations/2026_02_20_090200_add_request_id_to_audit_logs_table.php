<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('audit_logs', 'request_id')) {
                $table->string('request_id', 100)->nullable()->after('meta_data');
                $table->index('request_id', 'audit_logs_request_id_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('audit_logs', 'request_id')) {
                $table->dropIndex('audit_logs_request_id_idx');
                $table->dropColumn('request_id');
            }
        });
    }
};

