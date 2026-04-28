<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table): void {
            if (! Schema::hasColumn('delivery_notes', 'order_note_id')) {
                $table->foreignId('order_note_id')
                    ->nullable()
                    ->after('customer_ship_location_id')
                    ->constrained('order_notes')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
                $table->index(['order_note_id', 'note_date'], 'delivery_notes_order_note_date_idx');
            }
        });

        Schema::table('delivery_note_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('delivery_note_items', 'order_note_item_id')) {
                $table->foreignId('order_note_item_id')
                    ->nullable()
                    ->after('delivery_note_id')
                    ->constrained('order_note_items')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
                $table->index(['order_note_item_id', 'product_id'], 'delivery_note_items_order_item_product_idx');
            }
        });

        Schema::table('sales_invoice_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('sales_invoice_items', 'delivery_note_item_id')) {
                $table->foreignId('delivery_note_item_id')
                    ->nullable()
                    ->after('order_note_item_id')
                    ->constrained('delivery_note_items')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
                $table->index(['delivery_note_item_id', 'product_id'], 'sales_invoice_items_delivery_item_product_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoice_items', function (Blueprint $table): void {
            if (Schema::hasColumn('sales_invoice_items', 'delivery_note_item_id')) {
                $table->dropIndex('sales_invoice_items_delivery_item_product_idx');
                $table->dropConstrainedForeignId('delivery_note_item_id');
            }
        });

        Schema::table('delivery_note_items', function (Blueprint $table): void {
            if (Schema::hasColumn('delivery_note_items', 'order_note_item_id')) {
                $table->dropIndex('delivery_note_items_order_item_product_idx');
                $table->dropConstrainedForeignId('order_note_item_id');
            }
        });

        Schema::table('delivery_notes', function (Blueprint $table): void {
            if (Schema::hasColumn('delivery_notes', 'order_note_id')) {
                $table->dropIndex('delivery_notes_order_note_date_idx');
                $table->dropConstrainedForeignId('order_note_id');
            }
        });
    }
};
