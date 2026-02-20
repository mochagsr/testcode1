<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('sales_invoices', 'school_bulk_transaction_id')) {
                $table->foreignId('school_bulk_transaction_id')
                    ->nullable()
                    ->after('customer_ship_location_id')
                    ->constrained('school_bulk_transactions')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('sales_invoices', 'school_bulk_location_id')) {
                $table->foreignId('school_bulk_location_id')
                    ->nullable()
                    ->after('school_bulk_transaction_id')
                    ->constrained('school_bulk_transaction_locations')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('sales_invoices', 'school_bulk_location_id')) {
                $table->dropConstrainedForeignId('school_bulk_location_id');
            }
            if (Schema::hasColumn('sales_invoices', 'school_bulk_transaction_id')) {
                $table->dropConstrainedForeignId('school_bulk_transaction_id');
            }
        });
    }
};

