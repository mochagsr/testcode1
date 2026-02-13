<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->index(['is_canceled', 'invoice_date'], 'si_canceled_invoice_date_idx');
            $table->index(['invoice_date', 'id'], 'si_invoice_date_id_idx');
        });

        Schema::table('sales_returns', function (Blueprint $table): void {
            $table->index(['is_canceled', 'return_date'], 'sr_canceled_return_date_idx');
            $table->index(['return_date', 'id'], 'sr_return_date_id_idx');
        });

        Schema::table('delivery_notes', function (Blueprint $table): void {
            $table->index(['is_canceled', 'note_date'], 'dn_canceled_note_date_idx');
            $table->index(['note_date', 'id'], 'dn_note_date_id_idx');
        });

        Schema::table('order_notes', function (Blueprint $table): void {
            $table->index(['is_canceled', 'note_date'], 'on_canceled_note_date_idx');
            $table->index(['note_date', 'id'], 'on_note_date_id_idx');
        });

        Schema::table('receivable_payments', function (Blueprint $table): void {
            $table->index(['is_canceled', 'payment_date'], 'rp_canceled_payment_date_idx');
            $table->index(['payment_date', 'id'], 'rp_payment_date_id_idx');
        });

        Schema::table('receivable_ledgers', function (Blueprint $table): void {
            $table->index(['customer_id', 'entry_date', 'id'], 'rl_customer_entry_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('receivable_ledgers', function (Blueprint $table): void {
            $table->dropIndex('rl_customer_entry_id_idx');
        });

        Schema::table('receivable_payments', function (Blueprint $table): void {
            $table->dropIndex('rp_canceled_payment_date_idx');
            $table->dropIndex('rp_payment_date_id_idx');
        });

        Schema::table('order_notes', function (Blueprint $table): void {
            $table->dropIndex('on_canceled_note_date_idx');
            $table->dropIndex('on_note_date_id_idx');
        });

        Schema::table('delivery_notes', function (Blueprint $table): void {
            $table->dropIndex('dn_canceled_note_date_idx');
            $table->dropIndex('dn_note_date_id_idx');
        });

        Schema::table('sales_returns', function (Blueprint $table): void {
            $table->dropIndex('sr_canceled_return_date_idx');
            $table->dropIndex('sr_return_date_id_idx');
        });

        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->dropIndex('si_canceled_invoice_date_idx');
            $table->dropIndex('si_invoice_date_id_idx');
        });
    }
};
