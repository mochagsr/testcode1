<?php

declare(strict_types=1);

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
        // Sales Invoices - Critical for frequent filtering
        Schema::table('sales_invoices', function (Blueprint $table): void {
            // Check if indexes don't already exist
            $indexNames = Schema::getConnection()->getSchemaBuilder()->getIndexes('sales_invoices');
            $existingIndexes = array_column($indexNames, 'name');

            if (!in_array('idx_sales_invoices_customer', $existingIndexes)) {
                $table->index('customer_id', 'idx_sales_invoices_customer');
            }
            if (!in_array('idx_sales_invoices_invoice_date', $existingIndexes)) {
                $table->index('invoice_date', 'idx_sales_invoices_invoice_date');
            }
            if (!in_array('idx_sales_invoices_semester', $existingIndexes)) {
                $table->index('semester_period', 'idx_sales_invoices_semester');
            }
            if (!in_array('idx_sales_invoices_status', $existingIndexes)) {
                $table->index('is_canceled', 'idx_sales_invoices_status');
            }
            if (!in_array('idx_sales_invoices_combined', $existingIndexes)) {
                $table->index(['invoice_date', 'is_canceled'], 'idx_sales_invoices_combined');
            }
        });

        // Products - Critical for queries
        Schema::table('products', function (Blueprint $table): void {
            $indexNames = Schema::getConnection()->getSchemaBuilder()->getIndexes('products');
            $existingIndexes = array_column($indexNames, 'name');

            if (!in_array('idx_products_category', $existingIndexes)) {
                $table->index('item_category_id', 'idx_products_category');
            }
            if (!in_array('idx_products_active', $existingIndexes)) {
                $table->index('is_active', 'idx_products_active');
            }
            if (!in_array('idx_products_stock', $existingIndexes)) {
                $table->index('stock', 'idx_products_stock');
            }
        });

        // Customers
        Schema::table('customers', function (Blueprint $table): void {
            $indexNames = Schema::getConnection()->getSchemaBuilder()->getIndexes('customers');
            $existingIndexes = array_column($indexNames, 'name');

            if (!in_array('idx_customers_level', $existingIndexes)) {
                $table->index('customer_level_id', 'idx_customers_level');
            }
            if (!in_array('idx_customers_city', $existingIndexes)) {
                $table->index('city', 'idx_customers_city');
            }
        });

        // Outgoing Transactions
        Schema::table('outgoing_transactions', function (Blueprint $table): void {
            $indexNames = Schema::getConnection()->getSchemaBuilder()->getIndexes('outgoing_transactions');
            $existingIndexes = array_column($indexNames, 'name');

            if (!in_array('idx_outgoing_supplier', $existingIndexes)) {
                $table->index('supplier_id', 'idx_outgoing_supplier');
            }
            if (!in_array('idx_outgoing_date', $existingIndexes)) {
                $table->index('transaction_date', 'idx_outgoing_date');
            }
            if (!in_array('idx_outgoing_semester', $existingIndexes)) {
                $table->index('semester_period', 'idx_outgoing_semester');
            }
        });

        // ReceivableLedgers - Critical for financial queries
        Schema::table('receivable_ledgers', function (Blueprint $table): void {
            $indexNames = Schema::getConnection()->getSchemaBuilder()->getIndexes('receivable_ledgers');
            $existingIndexes = array_column($indexNames, 'name');

            if (!in_array('idx_receivable_customer', $existingIndexes)) {
                $table->index('customer_id', 'idx_receivable_customer');
            }
            if (!in_array('idx_receivable_entry_date', $existingIndexes)) {
                $table->index('entry_date', 'idx_receivable_entry_date');
            }
            if (!in_array('idx_receivable_combined', $existingIndexes)) {
                $table->index(['customer_id', 'entry_date'], 'idx_receivable_combined');
            }
        });

        // SalesReturn & Items
        Schema::table('sales_returns', function (Blueprint $table): void {
            $indexNames = Schema::getConnection()->getSchemaBuilder()->getIndexes('sales_returns');
            $existingIndexes = array_column($indexNames, 'name');

            if (!in_array('idx_sales_return_customer', $existingIndexes)) {
                $table->index('customer_id', 'idx_sales_return_customer');
            }
            if (!in_array('idx_sales_return_invoice', $existingIndexes)) {
                $table->index('sales_invoice_id', 'idx_sales_return_invoice');
            }
            if (!in_array('idx_sales_return_date', $existingIndexes)) {
                $table->index('return_date', 'idx_sales_return_date');
            }
        });

        // AuditLog - Important for auditing without killing performance
        Schema::table('audit_logs', function (Blueprint $table): void {
            $indexNames = Schema::getConnection()->getSchemaBuilder()->getIndexes('audit_logs');
            $existingIndexes = array_column($indexNames, 'name');

            if (!in_array('idx_audit_user', $existingIndexes)) {
                $table->index('user_id', 'idx_audit_user');
            }
            if (!in_array('idx_audit_created', $existingIndexes)) {
                $table->index('created_at', 'idx_audit_created');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->dropIndex('idx_sales_invoices_customer');
            $table->dropIndex('idx_sales_invoices_invoice_date');
            $table->dropIndex('idx_sales_invoices_semester');
            $table->dropIndex('idx_sales_invoices_status');
            $table->dropIndex('idx_sales_invoices_combined');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('idx_products_category');
            $table->dropIndex('idx_products_active');
            $table->dropIndex('idx_products_stock');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex('idx_customers_level');
            $table->dropIndex('idx_customers_city');
        });

        Schema::table('outgoing_transactions', function (Blueprint $table): void {
            $table->dropIndex('idx_outgoing_supplier');
            $table->dropIndex('idx_outgoing_date');
            $table->dropIndex('idx_outgoing_semester');
        });

        Schema::table('receivable_ledgers', function (Blueprint $table): void {
            $table->dropIndex('idx_receivable_customer');
            $table->dropIndex('idx_receivable_entry_date');
            $table->dropIndex('idx_receivable_combined');
        });

        Schema::table('sales_returns', function (Blueprint $table): void {
            $table->dropIndex('idx_sales_return_customer');
            $table->dropIndex('idx_sales_return_invoice');
            $table->dropIndex('idx_sales_return_date');
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex('idx_audit_user');
            $table->dropIndex('idx_audit_created');
        });
    }
};
