<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_ship_locations')) {
            Schema::create('customer_ship_locations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnUpdate()->cascadeOnDelete();
                $table->string('school_name', 150);
                $table->string('recipient_name', 150)->nullable();
                $table->string('recipient_phone', 30)->nullable();
                $table->string('city', 100)->nullable();
                $table->text('address')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['customer_id', 'school_name'], 'ship_locations_customer_school_idx');
                $table->index(['customer_id', 'is_active'], 'ship_locations_customer_active_idx');
            });
        }

        Schema::table('sales_invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('sales_invoices', 'customer_ship_location_id')) {
                $table->foreignId('customer_ship_location_id')
                    ->nullable()
                    ->after('customer_id')
                    ->constrained('customer_ship_locations')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('sales_invoices', 'ship_to_name')) {
                $table->string('ship_to_name', 150)->nullable()->after('payment_status');
            }
            if (! Schema::hasColumn('sales_invoices', 'ship_to_phone')) {
                $table->string('ship_to_phone', 30)->nullable()->after('ship_to_name');
            }
            if (! Schema::hasColumn('sales_invoices', 'ship_to_city')) {
                $table->string('ship_to_city', 100)->nullable()->after('ship_to_phone');
            }
            if (! Schema::hasColumn('sales_invoices', 'ship_to_address')) {
                $table->text('ship_to_address')->nullable()->after('ship_to_city');
            }
        });

        Schema::table('delivery_notes', function (Blueprint $table): void {
            if (! Schema::hasColumn('delivery_notes', 'customer_ship_location_id')) {
                $table->foreignId('customer_ship_location_id')
                    ->nullable()
                    ->after('customer_id')
                    ->constrained('customer_ship_locations')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }
        });

        if (! Schema::hasTable('school_bulk_transactions')) {
            Schema::create('school_bulk_transactions', function (Blueprint $table): void {
                $table->id();
                $table->string('transaction_number', 50)->unique();
                $table->date('transaction_date');
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnUpdate()->restrictOnDelete();
                $table->string('semester_period', 30)->nullable();
                $table->unsignedInteger('total_locations')->default(0);
                $table->unsignedInteger('total_items')->default(0);
                $table->text('notes')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
                $table->timestamps();

                $table->index(['transaction_date', 'customer_id'], 'school_bulk_transactions_date_customer_idx');
            });
        }

        if (! Schema::hasTable('school_bulk_transaction_locations')) {
            Schema::create('school_bulk_transaction_locations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_bulk_transaction_id')
                    ->constrained('school_bulk_transactions')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
                $table->foreignId('customer_ship_location_id')
                    ->nullable()
                    ->constrained('customer_ship_locations')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
                $table->string('school_name', 150);
                $table->string('recipient_name', 150)->nullable();
                $table->string('recipient_phone', 30)->nullable();
                $table->string('city', 100)->nullable();
                $table->text('address')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['school_bulk_transaction_id', 'sort_order'], 'school_bulk_txn_locations_sort_idx');
            });
        }

        if (! Schema::hasTable('school_bulk_transaction_items')) {
            Schema::create('school_bulk_transaction_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_bulk_transaction_id')
                    ->constrained('school_bulk_transactions')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnUpdate()->nullOnDelete();
                $table->string('product_code', 60)->nullable();
                $table->string('product_name', 200);
                $table->string('unit', 30)->nullable();
                $table->integer('quantity')->default(1);
                $table->integer('unit_price')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['school_bulk_transaction_id', 'sort_order'], 'school_bulk_txn_items_sort_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('school_bulk_transaction_items')) {
            Schema::drop('school_bulk_transaction_items');
        }

        if (Schema::hasTable('school_bulk_transaction_locations')) {
            Schema::drop('school_bulk_transaction_locations');
        }

        if (Schema::hasTable('school_bulk_transactions')) {
            Schema::drop('school_bulk_transactions');
        }

        Schema::table('delivery_notes', function (Blueprint $table): void {
            if (Schema::hasColumn('delivery_notes', 'customer_ship_location_id')) {
                $table->dropConstrainedForeignId('customer_ship_location_id');
            }
        });

        Schema::table('sales_invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('sales_invoices', 'customer_ship_location_id')) {
                $table->dropConstrainedForeignId('customer_ship_location_id');
            }
            if (Schema::hasColumn('sales_invoices', 'ship_to_name')) {
                $table->dropColumn('ship_to_name');
            }
            if (Schema::hasColumn('sales_invoices', 'ship_to_phone')) {
                $table->dropColumn('ship_to_phone');
            }
            if (Schema::hasColumn('sales_invoices', 'ship_to_city')) {
                $table->dropColumn('ship_to_city');
            }
            if (Schema::hasColumn('sales_invoices', 'ship_to_address')) {
                $table->dropColumn('ship_to_address');
            }
        });

        if (Schema::hasTable('customer_ship_locations')) {
            Schema::drop('customer_ship_locations');
        }
    }
};

