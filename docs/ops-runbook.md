# OPS Runbook (Backup, Restore, Recovery, Closing)

## 1. Backup nyata harian
- Jadwal otomatis:
  - `app:db-backup --gzip` setiap hari `01:00`.
  - `app:db-restore-test` setiap Minggu `02:00`.
  - `app:integrity-check` setiap hari `03:00`.
- Pastikan scheduler aktif di server:
  - Cron Linux: `* * * * * cd /path/app && php artisan schedule:run >> /dev/null 2>&1`

## 2. Uji restore berkala
- Manual:
  - `php artisan app:db-backup --gzip`
  - `php artisan app:db-restore-test`
- Hasil harus `Restore test passed`.
- Jika gagal, cek:
  - kredensial DB
  - akses file backup
  - utilitas `mysql/mysqldump` tersedia

## 3. SOP Recovery
1. Freeze transaksi (maintenance mode / disable login non-admin).
2. Ambil backup terakhir yang valid.
3. Restore ke DB baru (staging/temporary) dulu.
4. Validasi cepat:
   - login admin
   - list invoice
   - mutasi piutang
   - saldo customer/supplier
5. Jika valid, cutover ke DB produksi.
6. Jalankan `php artisan app:integrity-check`.
7. Buka akses user kembali.

## 4. SOP tutup buku semester
1. Pastikan pembayaran/retur sudah final.
2. Admin jalankan tutup semester via menu Settings.
3. Cek badge lock pada modul transaksi/piutang.
4. Jika ada koreksi, admin boleh override lock lalu edit.
5. Setelah koreksi, lock ulang semester.

## 5. Monitoring error
- Gunakan `alerts` log channel untuk error penting.
- Integrasikan file log alerts ke notifikasi (email/slack) di level server.

