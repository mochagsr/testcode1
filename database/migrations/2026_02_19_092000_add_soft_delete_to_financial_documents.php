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
            $table->softDeletes();
        });
        Schema::table('sales_returns', function (Blueprint $table): void {
            $table->softDeletes();
        });
        Schema::table('receivable_payments', function (Blueprint $table): void {
            $table->softDeletes();
        });
        Schema::table('outgoing_transactions', function (Blueprint $table): void {
            $table->softDeletes();
        });
        Schema::table('supplier_payments', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
        Schema::table('sales_returns', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
        Schema::table('receivable_payments', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
        Schema::table('outgoing_transactions', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
        Schema::table('supplier_payments', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
