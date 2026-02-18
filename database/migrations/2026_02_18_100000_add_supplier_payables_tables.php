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
        Schema::table('suppliers', function (Blueprint $table): void {
            if (!Schema::hasColumn('suppliers', 'outstanding_payable')) {
                $table->integer('outstanding_payable')->default(0)->after('notes');
            }
        });

        Schema::create('supplier_payments', function (Blueprint $table): void {
            $table->id();
            $table->string('payment_number', 50)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnUpdate()->restrictOnDelete();
            $table->date('payment_date');
            $table->string('proof_number', 80)->nullable();
            $table->integer('amount');
            $table->string('amount_in_words', 255)->nullable();
            $table->string('supplier_signature', 120)->nullable();
            $table->string('user_signature', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->boolean('is_canceled')->default(false);
            $table->timestamp('canceled_at')->nullable();
            $table->foreignId('canceled_by_user_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('cancel_reason', 1000)->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'payment_date']);
            $table->index(['is_canceled', 'payment_date']);
        });

        Schema::create('supplier_ledgers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('outgoing_transaction_id')->nullable()->constrained('outgoing_transactions')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('supplier_payment_id')->nullable()->constrained('supplier_payments')->cascadeOnUpdate()->nullOnDelete();
            $table->date('entry_date');
            $table->string('period_code', 30)->nullable();
            $table->text('description')->nullable();
            $table->integer('debit')->default(0);
            $table->integer('credit')->default(0);
            $table->integer('balance_after')->default(0);
            $table->timestamps();

            $table->index(['supplier_id', 'entry_date']);
            $table->index('period_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_ledgers');
        Schema::dropIfExists('supplier_payments');

        Schema::table('suppliers', function (Blueprint $table): void {
            if (Schema::hasColumn('suppliers', 'outstanding_payable')) {
                $table->dropColumn('outstanding_payable');
            }
        });
    }
};
