# Backup DB dan Ops Health di aaPanel v8.0.1

Dokumen ini menjelaskan cara membuat backup database pertama di server `aaPanel`, cara supaya backup tersebut tercatat di menu `Ops Health`, dan cara membaca hasil restore drill.

Dokumen ini dipakai untuk:
- env `tes`
  - path project: `/www/wwwroot/teserpos.mitrasejatiberkah.com`
- env `prod`
  - path project: `/www/wwwroot/erpos.mitrasejaitberkah.com`

## 1. Yang dibaca Ops Health

Menu `Ops Health` membaca 2 hal utama:

1. **File backup database**
- folder:
  - `storage/app/backups`
  - `storage/app/backups/db`

2. **Log restore drill**
- tabel database:
  - `restore_drill_logs`

Artinya:
- kalau file backup belum ada, `Ops Health` akan menampilkan:
  - `Belum ada file backup.`
- kalau restore drill belum pernah dijalankan, `Ops Health` akan menampilkan:
  - `Belum ada restore drill log.`

## 2. Command yang dipakai

### Backup database

```bash
php artisan app:db-backup
```

### Restore drill

```bash
php artisan app:db-restore-test
```

### Smoke test operasional

```bash
php artisan app:smoke-test
```

### Integrity check

```bash
php artisan app:integrity-check
```

### Performance probe ringan

```bash
php artisan app:load-test-light --loops=80 --search=ang
```

## 3. Cara paling aman untuk backup awal

Untuk backup awal, jalankan urutan ini:

```bash
php artisan app:db-backup
php artisan app:db-restore-test
php artisan app:smoke-test
```

Kenapa urutannya begitu:
- `app:db-backup` membuat file backup pertama
- `app:db-restore-test` membuat log restore drill
- `app:smoke-test` memastikan `Ops Health` sekarang membaca backup dan restore drill

## 4. Penting: jangan pakai `--gzip` dulu untuk backup pertama

Backup pertama lebih aman tanpa `--gzip`.

Kenapa:
- command `app:db-restore-test` saat ini otomatis mencari file:
  - `*.sql`
- kalau backup pertama langsung digzip, restore drill otomatis bisa melewati file itu

Jadi untuk backup pertama:

```bash
php artisan app:db-backup
```

Setelah backup dan restore drill pertama tercatat, backup otomatis harian boleh tetap memakai `--gzip` lewat scheduler aplikasi.

## 5. Contoh di aaPanel

Di aaPanel `v8.0.1`, command ini biasanya dijalankan dari menu:
- `Terminal`

### Env tes

```bash
cd /www/wwwroot/teserpos.mitrasejatiberkah.com
php artisan app:db-backup
php artisan app:db-restore-test
php artisan app:smoke-test
```

### Env prod

```bash
cd /www/wwwroot/erpos.mitrasejaitberkah.com
php artisan app:db-backup
php artisan app:db-restore-test
php artisan app:smoke-test
```

## 6. Contoh output yang normal

### Hasil backup

```text
Backup created: /www/wwwroot/teserpos.mitrasejatiberkah.com/storage/app/backups/db/backup-20260403-094100.sql
```

### Hasil restore drill berhasil

```text
Restore test passed for backup: /www/wwwroot/teserpos.mitrasejatiberkah.com/storage/app/backups/db/backup-20260403-094100.sql
```

### Hasil restore drill yang masih normal di aaPanel

Kalau user database server tidak punya hak `CREATE DATABASE` / `DROP DATABASE`, hasil berikut masih normal:

```text
Restore test skipped: database user cannot create/drop temporary databases on this server.
```

### Hasil smoke test yang sehat

```text
BACKUP_FILES      OK     Backup ditemukan: backups/db/backup-20260403-094100.sql
RESTORE_DRILL     WARN   Terakhir: 03-04-2026 09:41:02 / SKIPPED
INTEGRITY_CHECK   OK     Terakhir: 03-04-2026 09:45:32 / OK
PERFORMANCE_PROBE OK     Terakhir: 03-04-2026 09:45:33 / avg 2 ms
```

Catatan:
- di aaPanel, `RESTORE_DRILL = SKIPPED` masih aman kalau user DB tidak boleh membuat database sementara
- itu bukan blocker untuk env `tes`

## 7. Cara cek di Ops Health

Setelah command di atas selesai, buka:
- menu `Ops Health`

Yang seharusnya berubah:

1. `Latest Backup File`
- sudah menampilkan nama file backup

2. `Total Backup Files`
- minimal `1`

3. `Restore Drill`
- status terakhir:
  - `PASSED`
  - atau `SKIPPED` kalau user database server tidak punya hak membuat database sementara

4. `Integrity Check`
- sebaiknya `OK`

5. `Performance Probe`
- sebaiknya sudah ada timestamp dan angka rata-rata query ringan

## 8. Lokasi file backup

Default folder backup:
- `storage/app/backups/db`

Contoh file:
- `backup-20260403-094100.sql`
- `backup-20260403-094100.sql.gz`

## 9. Kapan pakai `app:db-backup` dan kapan pakai snapshot SQL

### `app:db-backup`
Dipakai untuk:
- backup harian
- backup sebelum update
- backup sebelum migration
- backup sebelum import besar
- backup awal supaya tercatat di `Ops Health`

### Snapshot SQL
Dipakai untuk:
- clone database ke env `tes`
- deploy pertama env `tes`
- salinan kondisi data kerja

Contoh file snapshot:
- `database/sql/tespgpos_mysql_test_snapshot.sql`

Jadi:
- **backup operasional** = `app:db-backup`
- **snapshot deploy / clone data** = file SQL di `database/sql`

## 10. Kapan wajib backup manual

Sebelum melakukan:
- `git pull`
- `php artisan migrate --force`
- import massal
- perubahan besar di master data
- rebuild finansial
- koreksi transaksi massal

Command:

```bash
php artisan app:db-backup
```

## 11. Contoh alur aman sebelum update

### Update kecil

```bash
cd /www/wwwroot/erpos.mitrasejaitberkah.com
php artisan app:db-backup
git pull origin master
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
php artisan app:smoke-test
```

### Update dengan migration

```bash
cd /www/wwwroot/erpos.mitrasejaitberkah.com
php artisan app:db-backup
git pull origin master
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
php artisan app:smoke-test
```

## 12. Kalau backup tidak muncul di Ops Health

Cek ini satu per satu:

1. command backup benar-benar dijalankan?

```bash
php artisan app:db-backup
```

2. file backup benar-benar ada?

```bash
ls -lah storage/app/backups/db
```

3. app membaca disk local normal?

```bash
php artisan app:smoke-test
```

4. cache belum dibersihkan?

```bash
php artisan optimize:clear
```

5. `Ops Health` dibuka di environment yang benar?
- jangan salah cek antara `tes` dan `prod`

## 13. Kalau restore drill gagal atau skipped

Penyebab paling umum:
- belum ada file backup `.sql`
- `mysql` / `mysqldump` tidak ada di server
- kredensial MySQL salah
- user DB tidak punya izin buat database sementara

Yang perlu dicek:

```bash
php artisan app:db-restore-test
```

Kemungkinan hasil:
- `Restore test passed ...`
  - aman
- `Restore test skipped ...`
  - masih normal untuk aaPanel jika user DB dibatasi
- `Failed preparing temporary database.`
  - cek hak akses DB dan konfigurasi MySQL

## 14. Jadwal otomatis yang sudah ada

Di scheduler app:
- backup:
  - `php artisan app:db-backup --gzip`
  - setiap hari `01:00`
- restore drill:
  - `php artisan app:db-restore-test`
  - setiap Minggu `02:00`
- integrity check:
  - `php artisan app:integrity-check`
  - setiap hari `03:00`

Supaya scheduler berjalan, cron server harus aktif:

```bash
* * * * * cd /www/wwwroot/erpos.mitrasejaitberkah.com && php artisan schedule:run >> /dev/null 2>&1
```

## 15. Rekomendasi operasional nyata

Untuk environment `tes`:
- backup manual pertama setelah deploy
- restore drill manual pertama
- lalu cek `Ops Health`

Untuk environment `prod`:
- backup manual pertama setelah go-live
- restore drill di jam sepi
- backup lagi sebelum update besar

## 16. Checklist singkat

Kalau kamu cuma butuh versi singkat:

### Env tes

```bash
cd /www/wwwroot/teserpos.mitrasejatiberkah.com
php artisan app:db-backup
php artisan app:db-restore-test
php artisan app:integrity-check
php artisan app:load-test-light --loops=80 --search=ang
php artisan app:smoke-test
```

### Env prod

```bash
cd /www/wwwroot/erpos.mitrasejaitberkah.com
php artisan app:db-backup
php artisan app:db-restore-test
php artisan app:integrity-check
php artisan app:load-test-light --loops=80 --search=ang
php artisan app:smoke-test
```

Lalu buka:
- `Ops Health`

Pastikan:
- `Latest Backup File` terisi
- `Total Backup Files >= 1`
- `Restore Drill` sudah ada status
- `Integrity Check` tidak fail
- `Performance Probe` sudah tercatat

## 17. Dokumen terkait

- `docs/DEPLOY_AAPANEL.md`
- `docs/GO_LIVE_RUNBOOK.md`
- `docs/UAT_AAPANEL_POST_DEPLOY.md`
