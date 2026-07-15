<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('item_categories', function (Blueprint $table) {
            $table->string('type', 20)->default('general')->after('name')->index();
        });

        // Backfill: any category already used by a raw-material product becomes
        // a raw-material category, so existing data stays correct.
        if (Schema::hasTable('products')) {
            DB::table('item_categories')
                ->whereIn('id', function ($query): void {
                    $query->select('item_category_id')
                        ->from('products')
                        ->where('product_type', 'raw_material')
                        ->whereNotNull('item_category_id');
                })
                ->update(['type' => 'raw_material']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_categories', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
