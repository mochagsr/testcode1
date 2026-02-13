<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->index(
                ['customer_id', 'is_canceled', 'balance', 'invoice_date', 'id'],
                'si_customer_open_balance_date_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->dropIndex('si_customer_open_balance_date_idx');
        });
    }
};

