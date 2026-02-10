<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receivable_payments', function (Blueprint $table): void {
            $table->id();
            $table->string('payment_number', 40)->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnUpdate()->restrictOnDelete();
            $table->date('payment_date');
            $table->string('customer_address', 255)->nullable();
            $table->decimal('amount', 14, 2);
            $table->text('amount_in_words');
            $table->string('customer_signature', 120);
            $table->string('user_signature', 120);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receivable_payments');
    }
};
