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
        Schema::table('sales_invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('sales_invoices', 'order_note_id')) {
                $table->foreignId('order_note_id')
                    ->nullable()
                    ->after('school_bulk_location_id')
                    ->constrained('order_notes')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
                $table->index(['order_note_id', 'invoice_date'], 'sales_invoices_order_note_date_idx');
            }
        });

        Schema::table('sales_invoice_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('sales_invoice_items', 'order_note_item_id')) {
                $table->foreignId('order_note_item_id')
                    ->nullable()
                    ->after('sales_invoice_id')
                    ->constrained('order_note_items')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
                $table->index(['order_note_item_id', 'product_id'], 'sales_invoice_items_order_note_item_product_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_invoice_items', function (Blueprint $table): void {
            if (Schema::hasColumn('sales_invoice_items', 'order_note_item_id')) {
                $table->dropIndex('sales_invoice_items_order_note_item_product_idx');
                $table->dropConstrainedForeignId('order_note_item_id');
            }
        });

        Schema::table('sales_invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('sales_invoices', 'order_note_id')) {
                $table->dropIndex('sales_invoices_order_note_date_idx');
                $table->dropConstrainedForeignId('order_note_id');
            }
        });
    }
};

