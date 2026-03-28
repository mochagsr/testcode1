# Stress Test Guide

Panduan ringan untuk uji beban setelah deploy `tes`.

## Tujuan
- memastikan query list/search tetap responsif
- memastikan export tidak langsung membuat server berat
- memastikan integrity tetap aman setelah import/transaksi massal

## 1. Sebelum mulai
- pakai DB `tes`
- aktifkan queue worker
- aktifkan cron scheduler
- pastikan backup awal sudah dibuat

## 2. Probe bawaan aplikasi
Jalankan:

```bash
php artisan app:load-test-light --loops=80 --search=ang
```

Lalu cek hasil di `Ops Health`.

## 3. Skenario data yang disarankan
- Barang: `1.000+`
- Customer: `300+`
- Supplier: `100+`
- Faktur kredit: `500+`
- Mutasi piutang customer aktif: `100+` baris pada 1 customer

## 4. Halaman yang wajib diuji
- Barang
- Customer
- Supplier
- Piutang
- Piutang Global
- Piutang Semester
- Hutang Supplier
- Kartu Stok Supplier
- Audit Log

## 5. Tanda aplikasi masih aman
- list/search tetap responsif
- tidak ada `500 error`
- print/PDF/Excel tetap selesai
- queue tidak menumpuk lama
- `Ops Health` tidak menunjukkan anomali baru

## 6. Setelah stress test
Jalankan:

```bash
php artisan app:integrity-check
php artisan app:db-backup --gzip
```

Jika ada anomali, cek:
- `Ops Health`
- `Audit Log`
- `storage/logs/laravel.log`
