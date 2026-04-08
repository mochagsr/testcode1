<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outgoing_transactions', function (Blueprint $table): void {
            if (! Schema::hasColumn('outgoing_transactions', 'subtotal_before_tax')) {
                $table->integer('subtotal_before_tax')->default(0)->after('supplier_invoice_photo_path');
            }
            if (! Schema::hasColumn('outgoing_transactions', 'total_tax')) {
                $table->integer('total_tax')->default(0)->after('subtotal_before_tax');
            }
        });

        Schema::table('outgoing_transaction_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('outgoing_transaction_items', 'tax_percent')) {
                $table->decimal('tax_percent', 5, 2)->default(0)->after('unit_cost');
            }
            if (! Schema::hasColumn('outgoing_transaction_items', 'tax_amount')) {
                $table->integer('tax_amount')->default(0)->after('tax_percent');
            }
            if (! Schema::hasColumn('outgoing_transaction_items', 'line_subtotal')) {
                $table->integer('line_subtotal')->default(0)->after('tax_amount');
            }
        });

        DB::table('outgoing_transaction_items')
            ->where('line_subtotal', 0)
            ->update([
                'line_subtotal' => DB::raw('line_total'),
            ]);

        DB::table('outgoing_transactions')
            ->update([
                'subtotal_before_tax' => DB::raw('total'),
                'total_tax' => 0,
            ]);
    }

    public function down(): void
    {
        Schema::table('outgoing_transaction_items', function (Blueprint $table): void {
            if (Schema::hasColumn('outgoing_transaction_items', 'line_subtotal')) {
                $table->dropColumn('line_subtotal');
            }
            if (Schema::hasColumn('outgoing_transaction_items', 'tax_amount')) {
                $table->dropColumn('tax_amount');
            }
            if (Schema::hasColumn('outgoing_transaction_items', 'tax_percent')) {
                $table->dropColumn('tax_percent');
            }
        });

        Schema::table('outgoing_transactions', function (Blueprint $table): void {
            if (Schema::hasColumn('outgoing_transactions', 'total_tax')) {
                $table->dropColumn('total_tax');
            }
            if (Schema::hasColumn('outgoing_transactions', 'subtotal_before_tax')) {
                $table->dropColumn('subtotal_before_tax');
            }
        });
    }
};
