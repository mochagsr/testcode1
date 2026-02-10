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
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_level_id')->nullable()->constrained('customer_levels')->cascadeOnUpdate()->nullOnDelete();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->string('phone', 30)->nullable();
            $table->string('city', 100)->nullable()->index();
            $table->text('address')->nullable();
            $table->string('id_card_photo_path')->nullable();
            $table->decimal('outstanding_receivable', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
