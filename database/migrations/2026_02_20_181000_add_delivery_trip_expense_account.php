<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('accounts')->where('code', '5102')->exists();
        if (! $exists) {
            DB::table('accounts')->insert([
                'code' => '5102',
                'name' => 'Biaya Operasional Pengiriman',
                'type' => 'expense',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('accounts')->where('code', '5102')->delete();
    }
};
