# UAT aaPanel Post-Deploy

Gunakan checklist ini setiap selesai deploy ke environment `tes` sebelum update diteruskan ke `prod`.

## 1. Sanity check awal
- Buka halaman login.
- Login sebagai `admin`.
- Cek `Dashboard`, `Ops Health`, `Audit Log`.
- Pastikan tidak ada `500 error`.

## 2. Service check
- Cron `schedule:run` aktif.
- Queue worker aktif.
- `Ops Health` menampilkan environment dan DB yang benar.
- `Debug Mode` harus `OFF`.
- Jalankan `php artisan app:smoke-test` dan pastikan tidak ada status `FAIL`.

## 3. Master data
- Buka `Barang`, `Customer`, `Supplier`, `Kategori Barang`.
- Coba `search`.
- Coba `Export Excel`.
- Coba `Print` dan `PDF` di halaman yang punya report.

## 4. Transaksi utama
- Buat `Faktur Penjualan` tunai.
- Buat `Faktur Penjualan` kredit.
- Buat `Retur Penjualan`.
- Buat `Surat Jalan`.
- Buat `Surat Pesanan`.
- Buat `Tanda Terima Barang`.
- Cek semua nomor dokumen format `DDMMYYYY`.

## 5. Piutang
- Buka `Piutang`.
- Cek `Piutang Global`.
- Cek `Piutang Semester`.
- Print global.
- Print per customer.
- Buat `Bayar Piutang`.
- Pastikan mutasi piutang berubah benar.

## 6. Hutang supplier
- Buka `Hutang Supplier`.
- Filter supplier + tahun + bulan.
- Print/PDF/Excel report.
- Buka `Kartu Stok Supplier`.
- Print/PDF/Excel report.
- Uji `Tutup Tahun` supplier.

## 7. Locking
- Coba `Tutup Semester` global di `Pengaturan`.
- Coba `Tutup Semester Customer` di `Piutang`.
- Pastikan transaksi baru tertahan sesuai aturan.
- Pastikan `Supplier` tetap pakai lock per tahun, bukan per semester.

## 8. Export dan print
- Uji `Print`, `PDF`, `Excel` dari:
  - Barang
  - Piutang Global
  - Piutang Semester
  - Hutang Supplier
  - Kartu Stok Supplier
- Pastikan tidak ada layout pecah.

## 9. Audit dan koreksi
- Buat 1 transaksi lalu cek muncul di `Audit Log`.
- Filter audit per modul.
- Coba `Wizard Koreksi`.
- Pastikan approval/pending terlihat.

## 10. Backup dan guardrail
- Jalankan:
  - `php artisan app:db-backup --gzip`
  - `php artisan app:integrity-check`
  - `php artisan app:load-test-light --loops=80 --search=ang`
- Cek hasilnya di `Ops Health`.

## Exit criteria
- Tidak ada error besar.
- Print/PDF/Excel aman.
- Lock semester/tahun sesuai aturan.
- Backup, integrity, dan perf probe tercatat.
- Admin sign-off untuk lanjut ke `prod`.
