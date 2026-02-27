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
        Schema::table('order_notes', function (Blueprint $table): void {
            if (! Schema::hasColumn('order_notes', 'address')) {
                $table->text('address')->nullable()->after('customer_phone');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_notes', function (Blueprint $table): void {
            if (Schema::hasColumn('order_notes', 'address')) {
                $table->dropColumn('address');
            }
        });
    }
};

