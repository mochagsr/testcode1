<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            if (! Schema::hasColumn('customers', 'phone_secondary')) {
                $table->string('phone_secondary', 30)->nullable()->after('phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            if (Schema::hasColumn('customers', 'phone_secondary')) {
                $table->dropColumn('phone_secondary');
            }
        });
    }
};
