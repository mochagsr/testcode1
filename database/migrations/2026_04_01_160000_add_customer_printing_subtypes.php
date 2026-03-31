<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_printing_subtypes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('normalized_name', 120);
            $table->timestamps();
            $table->unique(['customer_id', 'normalized_name'], 'cust_print_subtype_unique');
        });

        foreach (['sales_invoices', 'sales_returns', 'order_notes', 'delivery_notes'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->foreignId('customer_printing_subtype_id')->nullable()->after('transaction_type')
                    ->constrained('customer_printing_subtypes')->nullOnDelete();
                $table->string('printing_subtype_name', 120)->nullable()->after('customer_printing_subtype_id');
            });
        }

        Schema::table('receivable_ledgers', function (Blueprint $table): void {
            $table->foreignId('customer_printing_subtype_id')->nullable()->after('transaction_type')
                ->constrained('customer_printing_subtypes')->nullOnDelete();
            $table->string('printing_subtype_name', 120)->nullable()->after('customer_printing_subtype_id');
            $table->index('printing_subtype_name');
        });
    }

    public function down(): void
    {
        Schema::table('receivable_ledgers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_printing_subtype_id');
            $table->dropIndex(['printing_subtype_name']);
            $table->dropColumn('printing_subtype_name');
        });

        foreach (['sales_invoices', 'sales_returns', 'order_notes', 'delivery_notes'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('customer_printing_subtype_id');
                $table->dropColumn('printing_subtype_name');
            });
        }

        Schema::dropIfExists('customer_printing_subtypes');
    }
};
