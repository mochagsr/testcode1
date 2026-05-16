<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('receivable_payment_id')->nullable()->after('is_synthetic');
            $table->foreign('receivable_payment_id')
                ->references('id')
                ->on('receivable_payments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table): void {
            $table->dropForeign(['receivable_payment_id']);
            $table->dropColumn('receivable_payment_id');
        });
    }
};
