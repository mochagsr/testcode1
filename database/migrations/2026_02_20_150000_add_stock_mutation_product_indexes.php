<?php

declare(strict_types=1);

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
        if (! Schema::hasTable('stock_mutations')) {
            return;
        }

        Schema::table('stock_mutations', function (Blueprint $table): void {
            $indexNames = Schema::getConnection()->getSchemaBuilder()->getIndexes('stock_mutations');
            $existingIndexes = array_column($indexNames, 'name');

            if (! in_array('idx_stock_mutations_product_created', $existingIndexes, true)) {
                $table->index(['product_id', 'created_at'], 'idx_stock_mutations_product_created');
            }

            if (! in_array('idx_stock_mutations_type', $existingIndexes, true)) {
                $table->index('mutation_type', 'idx_stock_mutations_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('stock_mutations')) {
            return;
        }

        Schema::table('stock_mutations', function (Blueprint $table): void {
            $indexNames = Schema::getConnection()->getSchemaBuilder()->getIndexes('stock_mutations');
            $existingIndexes = array_column($indexNames, 'name');

            if (in_array('idx_stock_mutations_product_created', $existingIndexes, true)) {
                $table->dropIndex('idx_stock_mutations_product_created');
            }

            if (in_array('idx_stock_mutations_type', $existingIndexes, true)) {
                $table->dropIndex('idx_stock_mutations_type');
            }
        });
    }
};

