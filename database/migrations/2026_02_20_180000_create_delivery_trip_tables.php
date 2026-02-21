<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_trips', function (Blueprint $table): void {
            $table->id();
            $table->string('trip_number', 40)->unique();
            $table->date('trip_date');
            $table->string('driver_name', 120);
            $table->string('vehicle_plate', 40)->nullable();
            $table->unsignedSmallInteger('member_count')->default(0);
            $table->integer('fuel_cost')->default(0);
            $table->integer('toll_cost')->default(0);
            $table->integer('meal_cost')->default(0);
            $table->integer('other_cost')->default(0);
            $table->integer('total_cost')->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['trip_date', 'id'], 'delivery_trips_date_idx');
            $table->index('vehicle_plate', 'delivery_trips_plate_idx');
        });

        Schema::create('delivery_trip_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_trip_id')->constrained('delivery_trips')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('member_name', 120);
            $table->timestamps();

            $table->index(['delivery_trip_id', 'user_id'], 'delivery_trip_members_trip_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_trip_members');
        Schema::dropIfExists('delivery_trips');
    }
};

