<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS products_name_idx ON products (name)');
        DB::statement('CREATE INDEX IF NOT EXISTS products_code_idx ON products (code)');
        DB::statement('CREATE INDEX IF NOT EXISTS products_category_name_idx ON products (item_category_id, name)');

        DB::statement('CREATE INDEX IF NOT EXISTS customers_name_idx ON customers (name)');
        DB::statement('CREATE INDEX IF NOT EXISTS customers_phone_idx ON customers (phone)');
        DB::statement('CREATE INDEX IF NOT EXISTS customers_city_idx ON customers (city)');

        DB::statement('CREATE INDEX IF NOT EXISTS suppliers_name_idx ON suppliers (name)');
        DB::statement('CREATE INDEX IF NOT EXISTS suppliers_company_name_idx ON suppliers (company_name)');

        DB::statement('CREATE INDEX IF NOT EXISTS sales_invoices_number_idx ON sales_invoices (invoice_number)');
        DB::statement('CREATE INDEX IF NOT EXISTS sales_invoices_customer_date_idx ON sales_invoices (customer_id, invoice_date)');
        DB::statement('CREATE INDEX IF NOT EXISTS sales_invoices_semester_date_idx ON sales_invoices (semester_period, invoice_date)');

        DB::statement('CREATE INDEX IF NOT EXISTS receivable_ledgers_customer_date_idx ON receivable_ledgers (customer_id, entry_date)');
        DB::statement('CREATE INDEX IF NOT EXISTS receivable_ledgers_invoice_idx ON receivable_ledgers (sales_invoice_id)');

        DB::statement('CREATE INDEX IF NOT EXISTS supplier_ledgers_supplier_date_idx ON supplier_ledgers (supplier_id, entry_date)');
        DB::statement('CREATE INDEX IF NOT EXISTS supplier_ledgers_outgoing_idx ON supplier_ledgers (outgoing_transaction_id)');
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS products_name_idx');
            DB::statement('DROP INDEX IF EXISTS products_code_idx');
            DB::statement('DROP INDEX IF EXISTS products_category_name_idx');
            DB::statement('DROP INDEX IF EXISTS customers_name_idx');
            DB::statement('DROP INDEX IF EXISTS customers_phone_idx');
            DB::statement('DROP INDEX IF EXISTS customers_city_idx');
            DB::statement('DROP INDEX IF EXISTS suppliers_name_idx');
            DB::statement('DROP INDEX IF EXISTS suppliers_company_name_idx');
            DB::statement('DROP INDEX IF EXISTS sales_invoices_number_idx');
            DB::statement('DROP INDEX IF EXISTS sales_invoices_customer_date_idx');
            DB::statement('DROP INDEX IF EXISTS sales_invoices_semester_date_idx');
            DB::statement('DROP INDEX IF EXISTS receivable_ledgers_customer_date_idx');
            DB::statement('DROP INDEX IF EXISTS receivable_ledgers_invoice_idx');
            DB::statement('DROP INDEX IF EXISTS supplier_ledgers_supplier_date_idx');
            DB::statement('DROP INDEX IF EXISTS supplier_ledgers_outgoing_idx');
            return;
        }

        DB::statement('DROP INDEX products_name_idx ON products');
        DB::statement('DROP INDEX products_code_idx ON products');
        DB::statement('DROP INDEX products_category_name_idx ON products');
        DB::statement('DROP INDEX customers_name_idx ON customers');
        DB::statement('DROP INDEX customers_phone_idx ON customers');
        DB::statement('DROP INDEX customers_city_idx ON customers');
        DB::statement('DROP INDEX suppliers_name_idx ON suppliers');
        DB::statement('DROP INDEX suppliers_company_name_idx ON suppliers');
        DB::statement('DROP INDEX sales_invoices_number_idx ON sales_invoices');
        DB::statement('DROP INDEX sales_invoices_customer_date_idx ON sales_invoices');
        DB::statement('DROP INDEX sales_invoices_semester_date_idx ON sales_invoices');
        DB::statement('DROP INDEX receivable_ledgers_customer_date_idx ON receivable_ledgers');
        DB::statement('DROP INDEX receivable_ledgers_invoice_idx ON receivable_ledgers');
        DB::statement('DROP INDEX supplier_ledgers_supplier_date_idx ON supplier_ledgers');
        DB::statement('DROP INDEX supplier_ledgers_outgoing_idx ON supplier_ledgers');
    }
};
