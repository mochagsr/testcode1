<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('module', 60);
            $table->string('action', 60);
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_type', 120)->nullable();
            $table->json('payload')->nullable();
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('requested_by_user_id');
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('approval_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'approval_status_created_idx');
            $table->index(['module', 'action'], 'approval_module_action_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};

