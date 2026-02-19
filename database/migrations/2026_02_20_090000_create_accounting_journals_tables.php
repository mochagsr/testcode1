<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 120);
            $table->string('type', 30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('entry_number', 40)->unique();
            $table->date('entry_date');
            $table->string('entry_type', 40);
            $table->string('reference_type', 120)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id'], 'journal_entries_reference_idx');
            $table->index(['entry_type', 'entry_date'], 'journal_entries_type_date_idx');
        });

        Schema::create('journal_entry_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnUpdate()->restrictOnDelete();
            $table->integer('debit')->default(0);
            $table->integer('credit')->default(0);
            $table->string('memo', 255)->nullable();
            $table->timestamps();

            $table->index(['account_id', 'journal_entry_id'], 'journal_lines_account_entry_idx');
        });

        $now = now();
        DB::table('accounts')->insert([
            ['code' => '1101', 'name' => 'Kas', 'type' => 'asset', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => '1102', 'name' => 'Piutang Usaha', 'type' => 'asset', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => '1201', 'name' => 'Persediaan', 'type' => 'asset', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => '2101', 'name' => 'Hutang Supplier', 'type' => 'liability', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => '4101', 'name' => 'Penjualan', 'type' => 'revenue', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => '5101', 'name' => 'Retur Penjualan', 'type' => 'expense', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
    }
};

