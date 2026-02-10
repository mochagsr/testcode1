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
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_category_id')->constrained('item_categories')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('code', 60)->unique();
            $table->string('name', 200);
            $table->string('unit', 30)->nullable();
            $table->integer('stock')->default(0);
            $table->decimal('price_agent', 12, 2)->default(0);
            $table->decimal('price_sales', 12, 2)->default(0);
            $table->decimal('price_general', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
