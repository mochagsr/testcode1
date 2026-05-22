<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['delivery_notes', 'order_notes', 'school_bulk_transactions', 'stock_mutations', 'receivable_ledgers', 'supplier_ledgers'] as $tbl) {
            if (! Schema::hasColumn($tbl, 'deleted_at')) {
                Schema::table($tbl, function (Blueprint $table): void {
                    $table->softDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('supplier_ledgers', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('receivable_ledgers', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('stock_mutations', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('school_bulk_transactions', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('order_notes', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('delivery_notes', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
