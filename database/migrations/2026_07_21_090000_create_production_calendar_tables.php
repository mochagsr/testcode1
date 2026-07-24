<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spk', function (Blueprint $table): void {
            $table->id();
            $table->string('no_spk', 60)->unique();
            // Nomor urut auto (reset tiap semester) + semester asal untuk scope reset.
            $table->integer('nomor_urut')->nullable()->index();
            $table->string('semester_periode', 20)->nullable()->index();
            $table->string('konsumen', 150);
            $table->string('alamat', 200)->nullable();
            $table->string('jenis_order', 200)->nullable();
            $table->date('tanggal_order');
            $table->date('deadline_kirim');

            $table->boolean('pakai_web')->default(true);
            $table->boolean('pakai_sheet')->default(true);
            $table->string('jenis_cetak', 20)->default('penuh'); // penuh | sebagian
            $table->string('revisi_bagian', 200)->nullable();

            $table->string('finishing', 120)->nullable();
            $table->string('packing', 120)->nullable();
            $table->string('ukuran_jadi', 120)->nullable();
            $table->string('mesin_cover', 120)->nullable();
            $table->text('catatan')->nullable();

            // Bahan baku Web (Cetak Isi)
            $table->string('web_kertas', 120)->nullable();
            $table->string('web_warna', 120)->nullable();
            $table->string('web_mesin', 120)->nullable();
            $table->string('web_waste', 120)->nullable();

            // Bahan baku Sheet (Cetak Cover)
            $table->string('sheet_kertas', 120)->nullable();
            $table->string('sheet_warna', 120)->nullable();
            $table->string('sheet_mesin', 120)->nullable();
            $table->string('sheet_waste', 120)->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tanggal_order');
            $table->index('deadline_kirim');
            $table->index('konsumen');
        });

        Schema::create('spk_versi', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spk_id')->constrained('spk')->cascadeOnDelete();
            $table->string('nama', 120);
            $table->integer('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('spk_item', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spk_id')->constrained('spk')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('nama_barang', 200);
            $table->string('halaman', 40)->nullable();
            $table->string('kelas', 40)->nullable();
            $table->integer('cetak_isi')->nullable();   // qty Web
            $table->integer('cetak_sheet')->nullable(); // qty Sheet
            // Jumlah jadi aktual (diisi operator saat Finishing selesai) -> masuk stok produk umum.
            $table->integer('jumlah_jadi_realisasi')->nullable();
            // Berapa yang sudah diposting ke stok produk (untuk idempotensi).
            $table->integer('stock_posted_qty')->default(0);
            $table->integer('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('spk_item_versi', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spk_item_id')->constrained('spk_item')->cascadeOnDelete();
            $table->foreignId('spk_versi_id')->constrained('spk_versi')->cascadeOnDelete();
            $table->integer('qty')->nullable();
            $table->timestamps();
        });

        Schema::create('spk_stage', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spk_id')->constrained('spk')->cascadeOnDelete();
            $table->string('tahap', 20); // ctcp | web | sheet | finishing
            $table->string('pic', 120)->nullable();
            $table->string('mesin', 120)->nullable();
            $table->date('tanggal_rencana')->nullable();
            $table->date('tanggal_realisasi')->nullable();
            $table->integer('qty_rencana')->nullable();
            $table->integer('qty_realisasi')->nullable();
            $table->boolean('selesai')->default(false);
            $table->integer('urutan')->default(0);
            $table->timestamps();

            $table->index(['spk_id', 'tahap']);
        });

        Schema::create('spk_penanggung_jawab', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spk_id')->constrained('spk')->cascadeOnDelete();
            $table->string('jabatan', 120);
            $table->string('nama', 120)->nullable();
            $table->integer('urutan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spk_penanggung_jawab');
        Schema::dropIfExists('spk_stage');
        Schema::dropIfExists('spk_item_versi');
        Schema::dropIfExists('spk_item');
        Schema::dropIfExists('spk_versi');
        Schema::dropIfExists('spk');
    }
};
