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
        Schema::create('delivery_note_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_note_id')->constrained('delivery_notes')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnUpdate()->nullOnDelete();
            $table->string('product_code', 60)->nullable();
            $table->string('product_name', 200);
            $table->string('unit', 30)->nullable();
            $table->integer('quantity');
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_note_items');
    }
};
