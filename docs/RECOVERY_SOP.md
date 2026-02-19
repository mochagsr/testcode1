# SOP Recovery (Database & Financial Integrity)

## 1) Daily backup check
- Pastikan scheduler aktif.
- Verifikasi file backup baru muncul di `storage/app/backups/db`.

## 2) Weekly restore drill
- Jalankan:
```bash
php artisan app:db-restore-test
```
- Cek log hasil pada tabel `restore_drill_logs`.
- Status harus `passed`.

## 3) Financial integrity verification
- Jalankan:
```bash
php artisan app:integrity-check
```
- Jika ada mismatch, jalankan rebuild:
```bash
php artisan app:financial-rebuild
```

## 4) Journal rebuild (darurat)
- Jika jurnal korup / tidak sinkron:
```bash
php artisan app:financial-rebuild --rebuild-journal
```
- Catatan: ini membangun ulang jurnal dari dokumen transaksi aktif.

## 5) Incident flow
1. Freeze transaksi user (opsional via `finance_locked` user non-admin).
2. Backup kondisi saat ini.
3. Jalankan restore drill untuk validasi file backup.
4. Restore database produksi sesuai prosedur infra.
5. Jalankan `app:integrity-check`.
6. Jalankan `app:financial-rebuild` jika perlu.
7. Buka kembali akses transaksi.

