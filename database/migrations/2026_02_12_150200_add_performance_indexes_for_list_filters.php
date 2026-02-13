<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->index(['customer_id', 'is_canceled'], 'si_customer_canceled_idx');
            $table->index(['semester_period', 'is_canceled'], 'si_semester_canceled_idx');
            $table->index('invoice_date', 'si_invoice_date_idx');
            $table->index('payment_status', 'si_payment_status_idx');
        });

        Schema::table('sales_returns', function (Blueprint $table): void {
            $table->index(['customer_id', 'is_canceled'], 'sr_customer_canceled_idx');
            $table->index(['semester_period', 'is_canceled'], 'sr_semester_canceled_idx');
            $table->index('return_date', 'sr_return_date_idx');
        });

        Schema::table('delivery_notes', function (Blueprint $table): void {
            $table->index(['customer_id', 'is_canceled'], 'dn_customer_canceled_idx');
            $table->index('note_date', 'dn_note_date_idx');
        });

        Schema::table('order_notes', function (Blueprint $table): void {
            $table->index(['customer_id', 'is_canceled'], 'on_customer_canceled_idx');
            $table->index('note_date', 'on_note_date_idx');
        });

        Schema::table('receivable_ledgers', function (Blueprint $table): void {
            $table->index(['customer_id', 'period_code', 'entry_date'], 'rl_customer_period_entry_idx');
            $table->index('sales_invoice_id', 'rl_sales_invoice_idx');
        });

        Schema::table('receivable_payments', function (Blueprint $table): void {
            $table->index(['customer_id', 'is_canceled'], 'rp_customer_canceled_idx');
            $table->index('payment_date', 'rp_payment_date_idx');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->index('name', 'customers_name_idx');
            $table->index('outstanding_receivable', 'customers_outstanding_idx');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->index('name', 'products_name_idx');
            $table->index(['item_category_id', 'name'], 'products_category_name_idx');
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->index('name', 'suppliers_name_idx');
            $table->index('company_name', 'suppliers_company_name_idx');
        });

        Schema::table('outgoing_transactions', function (Blueprint $table): void {
            $table->index(['supplier_id', 'transaction_date'], 'ot_supplier_date_idx');
            $table->index('semester_period', 'ot_semester_idx');
        });
    }

    public function down(): void
    {
        Schema::table('outgoing_transactions', function (Blueprint $table): void {
            $table->dropIndex('ot_supplier_date_idx');
            $table->dropIndex('ot_semester_idx');
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropIndex('suppliers_name_idx');
            $table->dropIndex('suppliers_company_name_idx');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_name_idx');
            $table->dropIndex('products_category_name_idx');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex('customers_name_idx');
            $table->dropIndex('customers_outstanding_idx');
        });

        Schema::table('receivable_payments', function (Blueprint $table): void {
            $table->dropIndex('rp_customer_canceled_idx');
            $table->dropIndex('rp_payment_date_idx');
        });

        Schema::table('receivable_ledgers', function (Blueprint $table): void {
            $table->dropIndex('rl_customer_period_entry_idx');
            $table->dropIndex('rl_sales_invoice_idx');
        });

        Schema::table('order_notes', function (Blueprint $table): void {
            $table->dropIndex('on_customer_canceled_idx');
            $table->dropIndex('on_note_date_idx');
        });

        Schema::table('delivery_notes', function (Blueprint $table): void {
            $table->dropIndex('dn_customer_canceled_idx');
            $table->dropIndex('dn_note_date_idx');
        });

        Schema::table('sales_returns', function (Blueprint $table): void {
            $table->dropIndex('sr_customer_canceled_idx');
            $table->dropIndex('sr_semester_canceled_idx');
            $table->dropIndex('sr_return_date_idx');
        });

        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->dropIndex('si_customer_canceled_idx');
            $table->dropIndex('si_semester_canceled_idx');
            $table->dropIndex('si_invoice_date_idx');
            $table->dropIndex('si_payment_status_idx');
        });
    }
};
