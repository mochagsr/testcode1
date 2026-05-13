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
        Schema::table('delivery_trips', function (Blueprint $table): void {
            if (! Schema::hasColumn('delivery_trips', 'is_active')) {
                $table->boolean('is_active')->default(false)->after('notes');
            }

            if (! Schema::hasColumn('delivery_trips', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_trips', function (Blueprint $table): void {
            if (Schema::hasColumn('delivery_trips', 'completed_at')) {
                $table->dropColumn('completed_at');
            }

            if (Schema::hasColumn('delivery_trips', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
