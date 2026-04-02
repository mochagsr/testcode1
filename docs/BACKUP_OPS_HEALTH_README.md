# Backup DB dan Ops Health (aaPanel v8.0.1 / cPanel / VPS)

Dokumen ini menjelaskan cara membuat backup database pertama, cara supaya backup tersebut tercatat di menu `Ops Health`, dan cara memvalidasi restore drill.

Dokumen ini dipakai untuk:
- lokal / development
- server `tes`
- server `prod`
- aaPanel / cPanel / VPS biasa

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

Opsi tambahan:

```bash
php artisan app:db-backup --gzip
php artisan app:db-backup --path="D:\backup-db"
```

### Restore drill

```bash
php artisan app:db-restore-test
```

### Smoke test operasional

```bash
php artisan app:smoke-test
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

Backup pertama **lebih aman tanpa `--gzip`**.

Kenapa:
- command `app:db-restore-test` saat ini otomatis mencari file:
  - `*.sql`
- kalau kamu backup pakai:

```bash
php artisan app:db-backup --gzip
```

hasilnya menjadi:
- `*.sql.gz`

dan restore drill otomatis tidak akan menemukannya.

Jadi untuk backup pertama:

```bash
php artisan app:db-backup
```

Setelah backup dan restore drill pertama berhasil, backup otomatis harian boleh tetap memakai `--gzip`.

## 5. Contoh di lokal

Contoh path project lokal:

```bash
cd g:\laragon\www\tespgpos
php artisan app:db-backup
php artisan app:db-restore-test
php artisan app:smoke-test
```

Kalau database lokal masih `sqlite`, backup tetap tercatat di `Ops Health`, tetapi:
- restore drill bisa `skipped`
- karena restore drill penuh dipakai untuk driver `mysql`

## 6. Contoh di aaPanel

Di aaPanel `v8.0.1`, command ini biasanya dijalankan dari menu:
- `Terminal`

### Env tes

```bash
cd /www/wwwroot/pgpos-tes
php artisan app:db-backup
php artisan app:db-restore-test
php artisan app:smoke-test
```

### Env prod

```bash
cd /www/wwwroot/pgpos-prod
php artisan app:db-backup
php artisan app:db-restore-test
php artisan app:smoke-test
```

## 7. Contoh output yang normal

### Hasil backup

```text
Backup created: /www/wwwroot/pgpos-prod/storage/app/backups/db/backup-20260329-101500.sql
```

### Hasil restore drill

```text
Restore test passed for backup: /www/wwwroot/pgpos-prod/storage/app/backups/db/backup-20260329-101500.sql
```

Kalau di server seperti aaPanel user database tidak punya hak `CREATE DATABASE` / `DROP DATABASE`, hasil yang masih normal adalah:

```text
Restore test skipped: database user cannot create/drop temporary databases on this server.
```

### Hasil smoke test yang sehat

Contoh ringkas:

```text
BACKUP_FILES     OK    Backup ditemukan: backups/db/backup-20260329-101500.sql
RESTORE_DRILL    OK    Terakhir: 29-03-2026 10:17:40 / PASSED
```

## 8. Cara cek di Ops Health

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
  - atau `SKIPPED` kalau environment masih non-MySQL
  - atau `SKIPPED` kalau user database server tidak punya hak membuat database sementara

## 9. Lokasi file backup

Default folder backup:

- `storage/app/backups/db`

Contoh file:

- `backup-20260329-101500.sql`
- `backup-20260329-101500.sql.gz`
- untuk SQLite:
  - `backup-20260329-101500.sql.sqlite`

## 10. Kapan pakai `app:db-backup` dan kapan pakai snapshot SQL

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

## 11. Kapan wajib backup manual

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

## 12. Contoh alur aman sebelum update

### Update kecil

```bash
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

## 13. Kalau backup tidak muncul di Ops Health

Cek ini satu per satu:

1. command backup benar-benar dijalankan?

```bash
php artisan app:db-backup
```

2. file backup benar-benar ada?

Linux:

```bash
ls -lah storage/app/backups/db
```

Windows:

```powershell
Get-ChildItem storage\app\backups\db
```

3. app membaca disk local normal?
- jalankan:

```bash
php artisan app:smoke-test
```

4. cache belum dibersihkan?

```bash
php artisan optimize:clear
```

5. `Ops Health` dibuka di environment yang benar?
- jangan salah cek antara `tes` dan `prod`

## 14. Kalau restore drill gagal

Penyebab paling umum:
- belum ada file backup `.sql`
- `mysql` / `mysqldump` tidak ada di server
- kredensial MySQL salah
- user DB tidak punya izin buat database sementara

Yang perlu dicek:

```bash
php artisan app:db-restore-test
```

Kalau gagal, biasanya pesan yang muncul:
- `No SQL backup file found.`
- `Failed preparing temporary database.`
- `Restore command failed.`

## 15. Jadwal otomatis yang sudah ada

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
* * * * * cd /path/app && php artisan schedule:run >> /dev/null 2>&1
```

## 16. Rekomendasi operasional nyata

Untuk environment `tes`:
- backup manual pertama setelah deploy
- restore drill manual pertama
- lalu cek `Ops Health`

Untuk environment `prod`:
- backup manual pertama setelah go-live
- restore drill di jam sepi
- backup lagi sebelum update besar

## 17. Checklist singkat

Kalau kamu cuma butuh versi singkat:

```bash
php artisan app:db-backup
php artisan app:db-restore-test
php artisan app:smoke-test
```

Lalu buka:
- `Ops Health`

Pastikan:
- `Latest Backup File` terisi
- `Total Backup Files >= 1`
- `Restore Drill` sudah ada status

## 18. Dokumen terkait

- `docs/ops-runbook.md`
- `docs/RECOVERY_SOP.md`
- `docs/DEPLOY_AAPANEL.md`
- `docs/GO_LIVE_RUNBOOK.md`
- `docs/UAT_AAPANEL_POST_DEPLOY.md`
