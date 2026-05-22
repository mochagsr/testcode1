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
        if (! Schema::hasColumn('sales_invoices', 'paid_at')) {
            Schema::table('sales_invoices', function (Blueprint $table): void {
                $table->date('paid_at')->nullable()->after('payment_status')->index();
            });
        }

        // Backfill paid_at from the latest invoice_payment date for invoices that are already paid
        DB::statement("
            UPDATE sales_invoices
            SET paid_at = (
                SELECT MAX(payment_date)
                FROM invoice_payments
                WHERE sales_invoice_id = sales_invoices.id
            )
            WHERE payment_status = 'paid' AND paid_at IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->dropIndex(['paid_at']);
            $table->dropColumn('paid_at');
        });
    }
};
