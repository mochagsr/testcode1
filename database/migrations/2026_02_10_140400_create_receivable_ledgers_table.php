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
        Schema::create('receivable_ledgers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('sales_invoice_id')->nullable()->constrained('sales_invoices')->cascadeOnUpdate()->nullOnDelete();
            $table->date('entry_date');
            $table->string('period_code', 30)->nullable();
            $table->text('description')->nullable();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->decimal('balance_after', 14, 2)->default(0);
            $table->timestamps();

            $table->index(['customer_id', 'entry_date']);
            $table->index('period_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receivable_ledgers');
    }
};
