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
        Schema::create('outgoing_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('transaction_number', 50)->unique();
            $table->date('transaction_date');
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('semester_period', 30)->nullable();
            $table->string('note_number', 80)->nullable();
            $table->integer('total')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('outgoing_transaction_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('outgoing_transaction_id')->constrained('outgoing_transactions')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnUpdate()->nullOnDelete();
            $table->string('product_code', 60)->nullable();
            $table->string('product_name', 200);
            $table->string('unit', 30)->nullable();
            $table->integer('quantity');
            $table->integer('unit_cost');
            $table->integer('line_total');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outgoing_transaction_items');
        Schema::dropIfExists('outgoing_transactions');
    }
};
