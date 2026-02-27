<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_trips', function (Blueprint $table): void {
            if (! Schema::hasColumn('delivery_trips', 'assistant_name')) {
                $table->string('assistant_name', 120)->nullable()->after('driver_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_trips', function (Blueprint $table): void {
            if (Schema::hasColumn('delivery_trips', 'assistant_name')) {
                $table->dropColumn('assistant_name');
            }
        });
    }
};

