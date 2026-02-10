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
        Schema::create('sales_returns', function (Blueprint $table): void {
            $table->id();
            $table->string('return_number', 50)->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('sales_invoice_id')->nullable()->constrained('sales_invoices')->cascadeOnUpdate()->nullOnDelete();
            $table->date('return_date');
            $table->string('semester_period', 30)->nullable();
            $table->decimal('total', 14, 2)->default(0);
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_returns');
    }
};
