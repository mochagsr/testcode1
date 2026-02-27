<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outgoing_transaction_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('outgoing_transaction_items', 'item_category_id')) {
                $table->unsignedBigInteger('item_category_id')->nullable()->after('product_id');
                $table->index('item_category_id', 'oti_item_category_id_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('outgoing_transaction_items', function (Blueprint $table): void {
            if (Schema::hasColumn('outgoing_transaction_items', 'item_category_id')) {
                $table->dropIndex('oti_item_category_id_idx');
                $table->dropColumn('item_category_id');
            }
        });
    }
};

