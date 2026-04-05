# UAT aaPanel v8.0.1 Post-Deploy

Gunakan checklist ini setiap selesai deploy ke environment `tes` sebelum update diteruskan ke `prod`.

Menu aaPanel yang paling sering dipakai saat UAT:
- `Website`
- `Terminal`
- `Cron`
- `Databases`
- `Files`

## Akun default untuk uji login
- Admin
  - username: `admin`
  - email: `admin@pgpos.local`
  - password: `@Passwordadmin123#`
- User
  - username: `user`
  - email: `user@pgpos.local`
  - password: `@Passworduser123#`

Gunakan salah satu:
- username + password
- atau email + password

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
- Jalankan `php artisan test tests/Feature/PageLoadSmokeTest.php --stop-on-failure` untuk memastikan menu dan sub-menu utama tidak mengembalikan `500 error`.
- Di aaPanel, cek juga:
  - site aktif di `Website`
  - `PHP 8.3` aktif
  - `Running directory = public`

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
- Uji `Tutup Bulan` supplier.

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
  - `php artisan app:db-backup`
  - `php artisan app:db-restore-test`
  - `php artisan app:integrity-check`
  - `php artisan app:load-test-light --loops=80 --search=ang`
- Cek hasilnya di `Ops Health`.

## 11. Contoh command UAT singkat

```bash
cd /www/wwwroot/pgpos-tes
php artisan app:smoke-test
php artisan test tests/Feature/PageLoadSmokeTest.php --stop-on-failure
php artisan app:db-backup
php artisan app:db-restore-test
php artisan app:integrity-check
php artisan app:load-test-light --loops=80 --search=ang
```

## Exit criteria
- Tidak ada error besar.
- Print/PDF/Excel aman.
- Lock semester/tahun sesuai aturan.
- Backup, integrity, dan perf probe tercatat.
- Admin sign-off untuk lanjut ke `prod`.
