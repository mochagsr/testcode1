# Deploy PgPOS ERP ke cPanel via GitHub

Dokumen ini untuk uji deploy pertama di hosting cPanel dengan:

- source code dari GitHub
- database MySQL
- queue/session/cache berbasis database

Dokumen pendamping:

- `docs/DEPLOY_FINAL_CHECKLIST.md`
- `docs/CPANEL_TERMINAL_COMMANDS.md`
- `docs/ENV_PRODUCTION_REVIEW_TEMPLATE.md`

## 1. Prasyarat cPanel

Minimal yang harus tersedia:

- `PHP 8.3`
- `Composer`
- `MySQL / MariaDB`
- fitur `Git Version Control` atau akses terminal + `git`
- akses `Cron Jobs`

Opsional tapi berguna:

- akses `Terminal`
- bisa set `Document Root` domain/subdomain ke folder `public`

## 2. Buat database MySQL

Di cPanel:

1. buat database
2. buat user database
3. assign user ke database dengan `ALL PRIVILEGES`

Catat:

- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- biasanya `DB_HOST=localhost`

## 3. Clone repo dari GitHub

Jika cPanel punya `Git Version Control`:

1. clone repo ke folder misalnya:
   - `/home/USERNAME/repositories/tespgpos`
2. pastikan branch yang dipakai sesuai, misalnya `master`

Jika pakai terminal:

```bash
cd /home/USERNAME
git clone https://github.com/USERNAME/REPO.git repositories/tespgpos
cd repositories/tespgpos
```

## 4. Atur document root

Pilihan terbaik:

- arahkan domain/subdomain ke:
  - `/home/USERNAME/repositories/tespgpos/public`

Jika cPanel tidak mengizinkan:

- gunakan domain/subdomain terpisah yang document root-nya bisa diarahkan ke `public`
- jangan expose root project langsung ke web

## 5. Siapkan file `.env`

Gunakan contoh:

- `.env.cpanel.test.example` untuk uji deploy pertama
- `.env.cpanel.prod.example` untuk production final

Salin menjadi `.env`, lalu isi:

```env
APP_NAME=PgPOS-ERP
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id
APP_FALLBACK_LOCALE=en

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=cpanel_db_name
DB_USERNAME=cpanel_db_user
DB_PASSWORD=replace_me

CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=120

FILESYSTEM_DISK=public
```

Lalu generate key:

```bash
php artisan key:generate
```

## 6. Install dependency

Di root project:

```bash
composer install --no-dev --optimize-autoloader
```

Jika frontend asset sudah ikut di repo hasil build, tidak perlu `npm build`.
Kalau belum:

```bash
npm install
npm run build
```

## 7. Inisialisasi database

Ada 2 opsi.

### Opsi A - Direkomendasikan: migrate langsung

```bash
php artisan migrate --force
php artisan db:seed --force
```

### Opsi B - Import SQL yang sudah disiapkan

File yang sudah disiapkan untuk 2 environment:

- `database/sql/tespgpos_mysql_test_snapshot.sql`
  - snapshot MySQL dari database SQLite yang sedang dipakai sekarang
  - pakai ini untuk deploy `tes`
- `database/sql/tespgpos_mysql_prod_bootstrap.sql`
  - schema + seed dasar bersih
  - pakai ini untuk deploy `prod`

Import ke database MySQL hosting yang sesuai, lalu jalankan:

```bash
php artisan db:seed --force
```

Catatan:

- untuk `tes`, snapshot ini membuat data hosting sama dengan data lokal saat ini
- untuk `prod`, jalur paling aman tetap `php artisan migrate --force` lalu seed seperlunya

## 7A. Rekomendasi pemetaan environment

### Deploy tes

- file env:
  - `.env.cpanel.test.example`
- database:
  - `cpanel_pgpos_tes`
- SQL:
  - `database/sql/tespgpos_mysql_test_snapshot.sql`

### Deploy prod

- file env:
  - `.env.cpanel.prod.example`
- database:
  - `cpanel_pgpos_prod`
- SQL:
  - `database/sql/tespgpos_mysql_prod_bootstrap.sql`

## 7B. Regenerate snapshot `tes` dari database SQLite lokal

Kalau data lokal berubah dan kamu ingin deploy ulang env `tes` dengan data terbaru, jalankan:

```bash
php artisan app:sqlite-to-mysql-snapshot
```

Output default:

- `database/sql/tespgpos_mysql_test_snapshot.sql`

Command ini:

- membaca `database/database.sqlite`
- membangun schema MySQL dari migrasi terbaru
- menyalin semua data lokal ke MySQL
- menghasilkan dump SQL MySQL siap import untuk env `tes`

## 7C. Regenerate bootstrap `prod` dari migrasi + seeder terbaru

Kalau schema atau seed berubah dan kamu ingin menyiapkan ulang file SQL production:

```bash
php artisan app:mysql-prod-bootstrap
```

Output default:

- `database/sql/tespgpos_mysql_prod_bootstrap.sql`
- `database/sql/tespgpos_mysql_bootstrap.sql`

Command ini:

- membuat database MySQL sementara
- menjalankan `migrate:fresh`
- menjalankan `db:seed`
- menghasilkan dump SQL MySQL bersih untuk env `prod`

## 8. Jalankan optimasi production

```bash
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
```

## 9. Setup cron cPanel

### Scheduler

Tambahkan cron setiap 1 menit:

```bash
/usr/local/bin/php /home/USERNAME/repositories/tespgpos/artisan schedule:run >> /dev/null 2>&1
```

### Queue worker untuk shared hosting

Kalau tidak bisa daemon permanen, pakai cron:

```bash
/usr/local/bin/php /home/USERNAME/repositories/tespgpos/artisan queue:work --stop-when-empty --tries=1 >> /dev/null 2>&1
```

Interval:

- setiap 1 menit

Kalau hosting support supervisor/process manager, lebih baik pakai worker permanen.

## 10. Smoke test setelah deploy

Cek minimal ini:

1. login admin
2. buka dashboard
3. buka barang/customer/piutang
4. create 1 transaksi faktur
5. print 1 report
6. export PDF
7. export Excel
8. upload file/logo
9. queue export jalan
10. cron schedule jalan

## 11. Update dari GitHub

Di server:

```bash
cd /home/USERNAME/repositories/tespgpos
git pull origin master
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
```

## 12. Catatan operasional

- jangan deploy dengan `APP_DEBUG=true`
- jangan commit `.env`
- backup database sebelum `git pull` + `migrate`
- kalau pakai shared hosting, awasi `queue:work` dan `schedule:run`
- kalau ada error putih/500 setelah deploy, jalankan:

```bash
php artisan optimize:clear
```

## 13. Login awal seed default

Jika kamu menjalankan `db:seed`, user awal:

- admin:
  - username: `admin`
  - email: `admin@pgpos.local`
  - password: `@Passwordadmin123#`
- user:
  - username: `user`
  - email: `user@pgpos.local`
  - password: `@Passworduser123#`

Login bisa memakai username atau email.

Sebaiknya langsung ganti password setelah login pertama.
