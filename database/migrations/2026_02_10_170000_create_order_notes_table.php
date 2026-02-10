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
        Schema::create('order_notes', function (Blueprint $table): void {
            $table->id();
            $table->string('note_number', 50)->unique();
            $table->date('note_date');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->cascadeOnUpdate()->nullOnDelete();
            $table->string('customer_name', 150);
            $table->string('customer_phone', 30)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('created_by_name', 150)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_notes');
    }
};
