<?php

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
        Schema::table('delivery_notes', function (Blueprint $table): void {
            if (! Schema::hasColumn('delivery_notes', 'school_bulk_transaction_id')) {
                $table->foreignId('school_bulk_transaction_id')
                    ->nullable()
                    ->after('order_note_id')
                    ->constrained('school_bulk_transactions')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
                $table->index(['school_bulk_transaction_id', 'note_date'], 'delivery_notes_school_bulk_date_idx');
            }

            if (! Schema::hasColumn('delivery_notes', 'school_bulk_location_id')) {
                $table->foreignId('school_bulk_location_id')
                    ->nullable()
                    ->after('school_bulk_transaction_id')
                    ->constrained('school_bulk_transaction_locations')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
                $table->index(['school_bulk_location_id', 'note_date'], 'delivery_notes_school_bulk_location_date_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table): void {
            if (Schema::hasColumn('delivery_notes', 'school_bulk_location_id')) {
                $table->dropIndex('delivery_notes_school_bulk_location_date_idx');
                $table->dropConstrainedForeignId('school_bulk_location_id');
            }

            if (Schema::hasColumn('delivery_notes', 'school_bulk_transaction_id')) {
                $table->dropIndex('delivery_notes_school_bulk_date_idx');
                $table->dropConstrainedForeignId('school_bulk_transaction_id');
            }
        });
    }
};
