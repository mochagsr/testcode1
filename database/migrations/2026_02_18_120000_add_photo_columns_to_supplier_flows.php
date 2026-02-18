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
        Schema::table('outgoing_transactions', function (Blueprint $table): void {
            if (!Schema::hasColumn('outgoing_transactions', 'supplier_invoice_photo_path')) {
                $table->string('supplier_invoice_photo_path', 255)->nullable()->after('note_number');
            }
        });

        Schema::table('supplier_payments', function (Blueprint $table): void {
            if (!Schema::hasColumn('supplier_payments', 'payment_proof_photo_path')) {
                $table->string('payment_proof_photo_path', 255)->nullable()->after('proof_number');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outgoing_transactions', function (Blueprint $table): void {
            if (Schema::hasColumn('outgoing_transactions', 'supplier_invoice_photo_path')) {
                $table->dropColumn('supplier_invoice_photo_path');
            }
        });

        Schema::table('supplier_payments', function (Blueprint $table): void {
            if (Schema::hasColumn('supplier_payments', 'payment_proof_photo_path')) {
                $table->dropColumn('payment_proof_photo_path');
            }
        });
    }
};
