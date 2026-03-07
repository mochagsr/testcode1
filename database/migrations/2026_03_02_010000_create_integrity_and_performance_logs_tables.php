<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('integrity_check_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('customer_mismatch_count')->default(0);
            $table->unsignedInteger('supplier_mismatch_count')->default(0);
            $table->unsignedInteger('invalid_receivable_links')->default(0);
            $table->unsignedInteger('invalid_supplier_links')->default(0);
            $table->json('details')->nullable();
            $table->boolean('is_ok')->default(true);
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->index(['is_ok', 'checked_at'], 'integrity_logs_status_checked_idx');
            $table->index(['checked_at'], 'integrity_logs_checked_idx');
        });

        Schema::create('performance_probe_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('loops')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->unsignedInteger('avg_loop_ms')->default(0);
            $table->string('search_token', 80)->nullable();
            $table->json('metrics')->nullable();
            $table->timestamp('probed_at')->nullable();
            $table->timestamps();

            $table->index(['probed_at'], 'performance_probe_probed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_probe_logs');
        Schema::dropIfExists('integrity_check_logs');
    }
};

