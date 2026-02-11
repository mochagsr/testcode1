<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'sales_invoices',
            'sales_returns',
            'delivery_notes',
            'order_notes',
            'receivable_payments',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->boolean('is_canceled')->default(false)->after('updated_at');
                $table->timestamp('canceled_at')->nullable()->after('is_canceled');
                $table->unsignedBigInteger('canceled_by_user_id')->nullable()->after('canceled_at');
                $table->text('cancel_reason')->nullable()->after('canceled_by_user_id');
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'sales_invoices',
            'sales_returns',
            'delivery_notes',
            'order_notes',
            'receivable_payments',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn([
                    'is_canceled',
                    'canceled_at',
                    'canceled_by_user_id',
                    'cancel_reason',
                ]);
            });
        }
    }
};

