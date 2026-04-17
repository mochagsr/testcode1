# Retention Policy `erpos` Production

Panduan ini dipakai untuk skenario:
- aplikasi `erpos.mitrasejatiberkah.com`
- app server di `aaPanel`
- database di `AWS Lightsail Managed MySQL`
- target biaya hemat, termasuk bundle kecil seperti `$15 / 1 GB / 1 core`

Fokus dokumen ini:
- menjaga database production tetap ringan
- menjaga histori penting tetap aman
- memakai pola **semi-manual**, bukan hapus otomatis penuh

## Prinsip utama
- data operasional yang masih aktif tetap online
- data lama boleh diarsipkan, **bukan langsung dibuang**
- sebelum arsip/hapus, selalu:
  - backup penuh
  - restore test
  - verifikasi jumlah data
- untuk tabel finansial, arsip/hapus hanya dilakukan per periode yang jelas dan setelah dicek tidak mengganggu saldo aktif

## Rekomendasi awal untuk kasus ini

### Audit log
- **online**: `3 bulan`
- **arsip**: simpan dump/backup lebih lama sesuai kebutuhan internal

Catatan:
- untuk operasional harian, `3 bulan` biasanya cukup
- kalau nanti ada kebutuhan audit yang lebih panjang, arsip tetap bisa disimpan di backup SQL

### Transaksi ERP
- **online**: `60 bulan`
- contoh tabel:
  - `sales_invoices`
  - `sales_invoice_items`
  - `sales_returns`
  - `sales_return_items`
  - `delivery_notes`
  - `delivery_note_items`
  - `order_notes`
  - `order_note_items`
  - `outgoing_transactions`
  - `outgoing_transaction_items`
  - `receivable_ledgers`
  - `receivable_payments`
  - `supplier_payables`
  - `supplier_payments`

Catatan:
- `60 bulan` atau `5 tahun` masih masuk akal sebagai titik awal kalau transaksi nyata ternyata tidak setinggi perkiraan
- kalau nanti performa mulai turun atau storage mulai mepet, window ini bisa dipendekkan ke `36 bulan`

## Rekomendasi retention per kelompok data

### Selalu online
Data master sebaiknya tetap online semua:
- `users`
- `customers`
- `customer_levels`
- `products`
- `item_categories`
- `product_units`
- `suppliers`
- `customer_ship_locations`
- `app_settings`

### Online 60 bulan
Data transaksi inti:
- `sales_invoices`
- `sales_invoice_items`
- `sales_returns`
- `sales_return_items`
- `delivery_notes`
- `delivery_note_items`
- `order_notes`
- `order_note_items`
- `outgoing_transactions`
- `outgoing_transaction_items`
- `receivable_ledgers`
- `receivable_payments`
- `supplier_payables`
- `supplier_payments`
- `school_bulk_transactions`
- `school_bulk_transaction_items`
- `school_bulk_transaction_locations`

### Online 3 bulan
Data operasional pendukung:
- `audit_logs`
- `report_export_tasks`
- `integrity_logs`
- `performance_probe_logs`
- `restore_drill_logs`

### Online 6-12 bulan bila perlu
Kalau tim masih sering butuh jejak operasional:
- `audit_logs` bisa dinaikkan ke `6 bulan`
- tetap disarankan arsip, bukan dibiarkan menumpuk terus

## Semi-manual itu seperti apa
Pola semi-manual berarti:
- sistem tidak menghapus data lama sendiri diam-diam
- operator tetap memegang keputusan akhir
- tapi langkah kerja dibuat baku dan ringan

Alur semi-manual:
1. cek umur data dan ukuran database
2. tentukan periode yang mau diarsipkan
3. backup penuh
4. restore test
5. export arsip
6. verifikasi arsip
7. baru hapus dari production jika memang disetujui

## Apa yang masih manual saat ini
Untuk repo saat ini, pola semi-manual berarti:
- backup dan restore test sudah dibantu oleh command aplikasi
- tetapi keputusan arsip/hapus periode lama masih dilakukan operator
- export arsip periodik masih perlu dijalankan dengan prosedur ops yang disepakati

Artinya:
- aplikasi **belum** punya tombol atau command bawaan yang otomatis memindahkan transaksi lama ke database arsip
- aplikasi **belum** menghapus data lama sendiri secara diam-diam
- ini memang sengaja, supaya data finansial tidak berpindah/hilang tanpa verifikasi

## Kapan review retention dilakukan

### Review bulanan
Cek hal berikut setiap bulan:
- ukuran database
- kecepatan query laporan
- waktu export Excel/PDF
- pertumbuhan tabel besar

### Review tahunan
Setelah tutup tahun:
- putuskan apakah tahun lama tetap online
- atau dipindah ke arsip

## Trigger kapan harus mulai arsip serius
Untuk bundle kecil seperti `$15 / 1 GB / 1 core`, saya sarankan jangan menunggu sampai penuh.

Mulai review lebih serius kalau salah satu terjadi:
- storage database mulai mendekati `20-25 GB`
- report mulai terasa lambat di jam kerja
- export Excel/PDF makin berat
- query piutang / mutasi mulai lambat
- audit log tumbuh sangat cepat

Kalau storage mendekati `30 GB`, arsip atau upgrade sebaiknya jangan ditunda.

## SOP semi-manual per periode arsip

### 1. Tentukan periode arsip
Contoh:
- arsip semua data sebelum `2022-01-01`
- atau arsip semua data lebih tua dari `60 bulan`

### 2. Backup penuh dulu
Jalankan dari **folder app di server aaPanel**.

Catatan:
- command ini tetap dijalankan dari app server
- tetapi target database-nya tetap **managed DB AWS** yang aktif di `.env`
- jadi `cd /www/wwwroot/...` hanya menunjukkan lokasi project Laravel, bukan lokasi database

```bash
cd /www/wwwroot/erpos.mitrasejatiberkah.com
php artisan app:db-backup --gzip
```

### 3. Jalankan restore test

```bash
php artisan app:db-restore-test
```

Kalau restore drill gagal karena user DB tidak punya hak `CREATE DATABASE`, lihat:
- `docs/BACKUP_OPS_HEALTH_README.md`
- `docs/RECOVERY_SOP.md`

Catatan:
- karena memakai managed DB, restore drill tetap mencoba membuat database sementara di server DB terpisah
- jadi hak akses user MySQL tetap harus mendukung skenario restore test, atau gunakan user khusus restore drill

### 4. Export arsip
Export data lama ke:
- dump SQL penuh per periode
- atau database arsip terpisah

Contoh pendekatan paling aman:
- buat backup SQL tahunan
- simpan file hasil backup di storage backup
- kalau perlu buka histori lama, restore ke database arsip / staging

Catatan:
- pada tahap ini operator masih bisa memakai proses manual yang terkontrol
- misalnya export backup SQL penuh dulu, lalu simpan sebagai arsip tahunan
- kalau nanti dibutuhkan, baru kita bisa tambah SOP atau tool arsip yang lebih otomatis

### 5. Verifikasi arsip
Minimal cek:
- tabel utama ada
- jumlah baris sesuai perkiraan
- invoice/ledger/payment lama bisa dibuka di database arsip

### 6. Hapus dari production hanya setelah verifikasi
Jangan hapus data lama sebelum:
- backup penuh valid
- restore test lulus
- arsip sudah dicek

Kalau penghapusan periode lama memang jadi kebijakan resmi:
- lakukan saat maintenance window
- catat periode yang dihapus
- catat nama file backup / snapshot yang menjadi arsip rujukan

## Peringatan penting untuk data finansial
Untuk tabel seperti:
- `receivable_ledgers`
- `receivable_payments`
- `sales_invoices`
- `sales_returns`

jangan hapus per baris secara acak.

Gunakan aturan:
- arsip per periode yang jelas
- pastikan periode itu sudah selesai/tertutup
- pastikan saldo aktif customer/supplier tidak terganggu

Kalau belum yakin, lebih aman:
- data lama **tetap online**
- atau **hanya diekspor ke arsip dulu**, tanpa langsung dihapus

## Strategi aman tahap awal
Kalau belum siap arsip-hapus sungguhan, pakai tahap ini dulu:

### Tahap 1
- online:
  - transaksi 60 bulan
  - audit log 3 bulan
- arsip:
  - backup SQL rutin
- belum ada penghapusan production

### Tahap 2
Setelah pola backup dan restore test stabil:
- mulai arsipkan audit log lama
- mulai arsipkan export/performance/integrity logs lama

### Tahap 3
Kalau performa DB production mulai terasa:
- evaluasi arsip transaksi lebih tua dari 60 bulan
- atau upgrade bundle database

## Kapan lebih baik upgrade, bukan arsip
Kalau salah satu ini terjadi, upgrade DB sering lebih sehat daripada memaksa arsip terlalu cepat:
- transaksi nyata ternyata jauh lebih tinggi dari perkiraan
- banyak user akses bersamaan
- export/report sering dipakai
- rekap piutang aktif sangat besar
- kebutuhan histori online tetap panjang

Dalam kondisi itu:
- bundle `$30` atau `$60` akan lebih aman

## Rekomendasi final untuk kondisi saat ini
- `audit_logs` online `3 bulan`: **masuk akal**
- transaksi ERP online `60 bulan`: **masuk akal sebagai starting point**
- retention pakai pola **semi-manual**
- jangan hapus otomatis penuh dulu
- review retention minimal bulanan

## Checklist singkat operator
Setiap bulan:
1. cek ukuran database managed MySQL
2. cek performa laporan utama
3. cek growth `audit_logs`
4. cek backup terbaru
5. cek restore drill

Setiap akhir tahun:
1. backup penuh
2. restore test
3. tentukan periode arsip
4. export arsip
5. baru putuskan apakah data lama dihapus dari production
