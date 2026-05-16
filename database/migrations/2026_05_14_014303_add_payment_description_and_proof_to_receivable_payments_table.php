<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receivable_payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('receivable_payments', 'payment_description')) {
                $table->string('payment_description', 120)->nullable()->after('amount_in_words');
            }

            if (! Schema::hasColumn('receivable_payments', 'payment_proof_photo_path')) {
                $table->string('payment_proof_photo_path', 255)->nullable()->after('payment_description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('receivable_payments', function (Blueprint $table): void {
            if (Schema::hasColumn('receivable_payments', 'payment_proof_photo_path')) {
                $table->dropColumn('payment_proof_photo_path');
            }

            if (Schema::hasColumn('receivable_payments', 'payment_description')) {
                $table->dropColumn('payment_description');
            }
        });
    }
};
