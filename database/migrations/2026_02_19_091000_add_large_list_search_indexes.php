<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createIndexIfMissing('products', 'products_name_idx', 'name');
        $this->createIndexIfMissing('products', 'products_code_idx', 'code');
        $this->createIndexIfMissing('products', 'products_category_name_idx', 'item_category_id, name');

        $this->createIndexIfMissing('customers', 'customers_name_idx', 'name');
        $this->createIndexIfMissing('customers', 'customers_phone_idx', 'phone');
        $this->createIndexIfMissing('customers', 'customers_city_idx', 'city');

        $this->createIndexIfMissing('suppliers', 'suppliers_name_idx', 'name');
        $this->createIndexIfMissing('suppliers', 'suppliers_company_name_idx', 'company_name');

        $this->createIndexIfMissing('sales_invoices', 'sales_invoices_number_idx', 'invoice_number');
        $this->createIndexIfMissing('sales_invoices', 'sales_invoices_customer_date_idx', 'customer_id, invoice_date');
        $this->createIndexIfMissing('sales_invoices', 'sales_invoices_semester_date_idx', 'semester_period, invoice_date');

        $this->createIndexIfMissing('receivable_ledgers', 'receivable_ledgers_customer_date_idx', 'customer_id, entry_date');
        $this->createIndexIfMissing('receivable_ledgers', 'receivable_ledgers_invoice_idx', 'sales_invoice_id');

        $this->createIndexIfMissing('supplier_ledgers', 'supplier_ledgers_supplier_date_idx', 'supplier_id, entry_date');
        $this->createIndexIfMissing('supplier_ledgers', 'supplier_ledgers_outgoing_idx', 'outgoing_transaction_id');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('products', 'products_name_idx');
        $this->dropIndexIfExists('products', 'products_code_idx');
        $this->dropIndexIfExists('products', 'products_category_name_idx');
        $this->dropIndexIfExists('customers', 'customers_name_idx');
        $this->dropIndexIfExists('customers', 'customers_phone_idx');
        $this->dropIndexIfExists('customers', 'customers_city_idx');
        $this->dropIndexIfExists('suppliers', 'suppliers_name_idx');
        $this->dropIndexIfExists('suppliers', 'suppliers_company_name_idx');
        $this->dropIndexIfExists('sales_invoices', 'sales_invoices_number_idx');
        $this->dropIndexIfExists('sales_invoices', 'sales_invoices_customer_date_idx');
        $this->dropIndexIfExists('sales_invoices', 'sales_invoices_semester_date_idx');
        $this->dropIndexIfExists('receivable_ledgers', 'receivable_ledgers_customer_date_idx');
        $this->dropIndexIfExists('receivable_ledgers', 'receivable_ledgers_invoice_idx');
        $this->dropIndexIfExists('supplier_ledgers', 'supplier_ledgers_supplier_date_idx');
        $this->dropIndexIfExists('supplier_ledgers', 'supplier_ledgers_outgoing_idx');
    }

    private function createIndexIfMissing(string $table, string $indexName, string $columns): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        DB::statement(sprintf('CREATE INDEX %s ON %s (%s)', $indexName, $table, $columns));
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! $this->indexExists($table, $indexName)) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement(sprintf('DROP INDEX %s', $indexName));
            return;
        }

        DB::statement(sprintf('DROP INDEX %s ON %s', $indexName, $table));
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return DB::table('sqlite_master')
                ->where('type', 'index')
                ->where('tbl_name', $table)
                ->where('name', $indexName)
                ->exists();
        }

        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }
};
