<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restore_drill_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('backup_file', 255)->nullable();
            $table->string('status', 20);
            $table->integer('duration_ms')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('tested_at');
            $table->timestamps();

            $table->index(['status', 'tested_at'], 'restore_drill_status_tested_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restore_drill_logs');
    }
};

