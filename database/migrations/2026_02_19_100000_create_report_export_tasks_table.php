<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_export_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('dataset', 100);
            $table->string('format', 20);
            $table->string('status', 30)->default('queued');
            $table->json('filters')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at'], 'report_export_tasks_user_status_idx');
            $table->index(['dataset', 'format', 'created_at'], 'report_export_tasks_dataset_format_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_export_tasks');
    }
};
