<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_bulk_transaction_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('school_bulk_transaction_items', 'school_bulk_transaction_location_id')) {
                $table->foreignId('school_bulk_transaction_location_id')
                    ->nullable()
                    ->after('school_bulk_transaction_id')
                    ->constrained('school_bulk_transaction_locations')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('school_bulk_transaction_items', function (Blueprint $table): void {
            if (Schema::hasColumn('school_bulk_transaction_items', 'school_bulk_transaction_location_id')) {
                $table->dropConstrainedForeignId('school_bulk_transaction_location_id');
            }
        });
    }
};

