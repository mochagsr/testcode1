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
        Schema::create('system_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();
            $table->string('level')->default('critical');
            $table->string('title');
            $table->text('message')->nullable();
            $table->json('context')->nullable();
            $table->string('dedupe_key')->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by_user_id')->nullable();
            $table->timestamps();

            $table->index('resolved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_alerts');
    }
};
