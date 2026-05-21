<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('product_type', 20)->default('general')->after('is_active');
        });

        // Mark existing products that have been received from a supplier as raw_material.
        DB::statement("
            UPDATE products SET product_type = 'raw_material'
            WHERE id IN (
                SELECT DISTINCT product_id FROM outgoing_transaction_items
            )
        ");
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('product_type');
        });
    }
};
