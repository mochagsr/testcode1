<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            if (!Schema::hasColumn('audit_logs', 'before_data')) {
                $table->json('before_data')->nullable()->after('description');
            }
            if (!Schema::hasColumn('audit_logs', 'after_data')) {
                $table->json('after_data')->nullable()->after('before_data');
            }
            if (!Schema::hasColumn('audit_logs', 'meta_data')) {
                $table->json('meta_data')->nullable()->after('after_data');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $drop = [];
            if (Schema::hasColumn('audit_logs', 'before_data')) {
                $drop[] = 'before_data';
            }
            if (Schema::hasColumn('audit_logs', 'after_data')) {
                $drop[] = 'after_data';
            }
            if (Schema::hasColumn('audit_logs', 'meta_data')) {
                $drop[] = 'meta_data';
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
