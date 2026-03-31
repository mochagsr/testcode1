<?php

declare(strict_types=1);

use App\Support\TransactionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('sales_invoices', 'transaction_type')) {
                $table->string('transaction_type', 20)
                    ->default(TransactionType::PRODUCT)
                    ->after('semester_period');
            }
        });

        Schema::table('sales_returns', function (Blueprint $table): void {
            if (! Schema::hasColumn('sales_returns', 'transaction_type')) {
                $table->string('transaction_type', 20)
                    ->default(TransactionType::PRODUCT)
                    ->after('semester_period');
            }
        });

        Schema::table('delivery_notes', function (Blueprint $table): void {
            if (! Schema::hasColumn('delivery_notes', 'transaction_type')) {
                $table->string('transaction_type', 20)
                    ->default(TransactionType::PRODUCT)
                    ->after('customer_id');
            }
        });

        Schema::table('order_notes', function (Blueprint $table): void {
            if (! Schema::hasColumn('order_notes', 'transaction_type')) {
                $table->string('transaction_type', 20)
                    ->default(TransactionType::PRODUCT)
                    ->after('customer_id');
            }
        });

        Schema::table('receivable_ledgers', function (Blueprint $table): void {
            if (! Schema::hasColumn('receivable_ledgers', 'transaction_type')) {
                $table->string('transaction_type', 20)
                    ->nullable()
                    ->after('period_code');
                $table->index('transaction_type');
            }
        });

        DB::table('sales_invoices')
            ->whereNull('transaction_type')
            ->update(['transaction_type' => TransactionType::PRODUCT]);

        DB::table('sales_returns')
            ->whereNull('transaction_type')
            ->update(['transaction_type' => TransactionType::PRODUCT]);

        DB::table('delivery_notes')
            ->whereNull('transaction_type')
            ->update(['transaction_type' => TransactionType::PRODUCT]);

        DB::table('order_notes')
            ->whereNull('transaction_type')
            ->update(['transaction_type' => TransactionType::PRODUCT]);

        if (DB::getDriverName() === 'sqlite') {
            DB::statement("
                UPDATE receivable_ledgers
                SET transaction_type = (
                    SELECT sales_invoices.transaction_type
                    FROM sales_invoices
                    WHERE sales_invoices.id = receivable_ledgers.sales_invoice_id
                )
                WHERE sales_invoice_id IS NOT NULL
            ");
        } else {
            DB::statement("
                UPDATE receivable_ledgers rl
                INNER JOIN sales_invoices si ON si.id = rl.sales_invoice_id
                SET rl.transaction_type = si.transaction_type
                WHERE rl.sales_invoice_id IS NOT NULL
            ");
        }

        DB::table('receivable_ledgers')
            ->whereNull('transaction_type')
            ->update(['transaction_type' => TransactionType::PRODUCT]);
    }

    public function down(): void
    {
        Schema::table('receivable_ledgers', function (Blueprint $table): void {
            if (Schema::hasColumn('receivable_ledgers', 'transaction_type')) {
                $table->dropIndex(['transaction_type']);
                $table->dropColumn('transaction_type');
            }
        });

        Schema::table('order_notes', function (Blueprint $table): void {
            if (Schema::hasColumn('order_notes', 'transaction_type')) {
                $table->dropColumn('transaction_type');
            }
        });

        Schema::table('delivery_notes', function (Blueprint $table): void {
            if (Schema::hasColumn('delivery_notes', 'transaction_type')) {
                $table->dropColumn('transaction_type');
            }
        });

        Schema::table('sales_returns', function (Blueprint $table): void {
            if (Schema::hasColumn('sales_returns', 'transaction_type')) {
                $table->dropColumn('transaction_type');
            }
        });

        Schema::table('sales_invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('sales_invoices', 'transaction_type')) {
                $table->dropColumn('transaction_type');
            }
        });
    }
};
