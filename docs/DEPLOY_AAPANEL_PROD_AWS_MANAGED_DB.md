# Deploy ERPOS Production di aaPanel + AWS Lightsail Managed Database

Dokumen ini khusus untuk environment production:
- domain app: `erpos.mitrasejatiberkah.com`
- panel app server: `aaPanel v8.0.1`
- cloud provider: `AWS Lightsail`
- database production: `AWS Lightsail Managed Database` terpisah dari app server
- DNS publik: `Cloudflare`

Dokumen ini dipisahkan dari `docs/DEPLOY_AAPANEL.md` supaya jalur `teserpos` dan `erpos` tidak tercampur.

## 0. Arsitektur final production

Komponen production:
- App server:
  - `AWS Lightsail Instance`
  - OS `Ubuntu 24.04 LTS`
  - panel `aaPanel`
- Database server:
  - `AWS Lightsail Managed Database (MySQL)`
- App domain:
  - `erpos.mitrasejatiberkah.com`
- Folder app:
  - `/www/wwwroot/erpos.mitrasejatiberkah.com`
- Running directory:
  - `/www/wwwroot/erpos.mitrasejatiberkah.com/public`

Ringkasnya:
- aaPanel hanya menangani web app / file / PHP / cron / queue
- database production tidak berada di server aaPanel
- backup dibagi dua:
  - backup file app dari server
  - snapshot / backup database dari Lightsail Managed Database

## 1. Kapan dokumen ini dipakai

Gunakan dokumen ini kalau:
- kamu sedang menyiapkan `erpos` production
- app server tetap memakai `aaPanel`
- database production dipisah ke managed DB AWS
- kamu ingin `teserpos` tetap terpisah sebagai env tes

Jangan pakai dokumen ini untuk `teserpos`.
Untuk `teserpos`, gunakan:
- `docs/DEPLOY_AAPANEL.md`

## 2. Paket server yang disarankan

### App server production
Saran realistis:
- `4 vCPU`
- `8 GB RAM`
- `160 GB SSD`

Kalau traffic dan export/report cukup sering:
- `4 vCPU`
- `16 GB RAM`
- `160 GB SSD` atau lebih

### Managed database production
Minimal aman:
- `1 primary managed database`
- ukuran sesuaikan paket Lightsail yang setara kebutuhan awal
- aktifkan backup/snapshot terjadwal

Catatan:
- Laravel tetap memakai `DB_CONNECTION=mysql`
- walaupun engine di AWS adalah managed database

## 3. Port yang perlu dibuka di Lightsail app server

Buka port berikut di `Networking` Lightsail:
- `22` untuk SSH
- `80` untuk HTTP
- `443` untuk HTTPS
- port panel aaPanel kamu, misalnya `21218`

Tidak perlu membuka port database ke publik di app server, karena database sudah terpisah.

## 4. Buat Lightsail instance untuk app server

1. buat `Linux/Unix instance`
2. pilih image:
   - `Ubuntu 24.04 LTS`
3. beri nama misalnya:
   - `erpos-aapanel-prod-01`
4. attach `Static IP`
5. catat IP public final

Contoh variabel yang nanti dipakai di dokumen ini:
- `APP_SERVER_PUBLIC_IP`
- `AA_PANEL_PORT`

## 5. Install aaPanel di app server

Login SSH ke server lalu jalankan installer aaPanel.
Setelah selesai, catat:
- URL panel
- username
- password
- port panel

Contoh hasil:
- `https://APP_SERVER_PUBLIC_IP:21218/...`

Setelah login ke aaPanel, siapkan juga komponen dasar server:
- PHP `8.3`
- Composer terbaru yang kompatibel dengan PHP `8.3`
- Node.js `22.x` atau minimal `20.19+`
- `default-mysql-client` atau `mariadb-client` untuk import SQL dari terminal ke managed DB

## 6. Buat Lightsail Managed Database

Di `AWS Lightsail`:
1. buka menu `Databases`
2. buat database baru
3. pilih engine:
   - `MySQL`
4. beri nama yang jelas, misalnya:
   - `erpos-prod-db`
5. pilih region yang sama dengan app server
6. aktifkan backup otomatis / snapshot bila tersedia

Setelah database jadi, catat:
- endpoint / hostname database
- port database
- master username
- master password
- nama database final yang akan dipakai app

Contoh variabel:
- `ERPOS_DB_HOST`
- `ERPOS_DB_PORT`
- `ERPOS_DB_NAME`
- `ERPOS_DB_USER`
- `ERPOS_DB_PASSWORD`

## 7. Izinkan koneksi dari app server ke managed DB

Di pengaturan managed database:
- whitelist / trusted sources / allowed connections harus mengizinkan app server production
- kalau ada opsi pembatasan jaringan, izinkan IP dari app server production

Targetnya:
- app server bisa konek ke managed DB
- database tidak dibuka bebas ke publik kalau tidak perlu

## 8. Setup DNS di Cloudflare

Buat record berikut di Cloudflare:

### App production
- Type: `A`
- Name: `erpos`
- Value: `APP_SERVER_PUBLIC_IP`
- Proxy status:
  - `DNS only` saat setup awal SSL / verifikasi
  - bisa diubah ke `Proxied` setelah app stabil

### Opsional root domain
Kalau root domain mau diarahkan ke production:
- Type: `CNAME`
- Name: `@`
- Target: `erpos.mitrasejatiberkah.com`
- Proxy status sesuai kebutuhan

### SSL mode Cloudflare
Setelah SSL di server aktif, set:
- `SSL/TLS mode = Full (strict)`

## 9. Cek propagasi DNS

Dari Windows:

```bash
nslookup erpos.mitrasejatiberkah.com
```

Interpretasi:
- kalau `DNS only`, hasil bisa menunjuk langsung ke IP Lightsail
- kalau `Proxied`, hasil akan menunjuk ke IP Cloudflare

## 10. Buat website production di aaPanel

Di `Website -> Add site`:
- Domain:
  - `erpos.mitrasejatiberkah.com`
- Root dir:
  - `/www/wwwroot/erpos.mitrasejatiberkah.com`
- PHP version:
  - `8.3`
- Database:
  - tidak wajib dibuat di aaPanel untuk production ini, karena DB memakai managed DB terpisah

Setelah site dibuat:
- pastikan running directory diarahkan ke:
  - `/www/wwwroot/erpos.mitrasejatiberkah.com/public`
- pasang rewrite Laravel sesuai stack web server yang dipakai

## 11. Extension PHP 8.3 yang wajib aktif

Aktifkan minimal:
- `bcmath`
- `ctype`
- `curl`
- `dom`
- `exif`
- `fileinfo`
- `gd`
- `mbstring`
- `mysqli`
- `openssl`
- `pdo`
- `pdo_mysql`
- `tokenizer`
- `xml`
- `zip`

Bagus juga diaktifkan:
- `intl`
- `opcache`

## 11.1 Tool terminal yang disarankan

Di app server, siapkan tool ini juga:

```bash
apt update
apt install -y default-mysql-client unzip git curl
```

Catatan:
- `default-mysql-client` dipakai untuk command import ke managed DB
- kalau distro memilih paket lain, `mariadb-client` juga biasanya cukup

## 12. Clone code production

```bash
cd /www/wwwroot/erpos.mitrasejatiberkah.com
git clone https://github.com/mochagsr/testcode1.git .
```

Kalau folder sudah dibuat oleh aaPanel dan tidak kosong, masuk ke folder itu lalu clone ke `.` dengan hati-hati.

## 13. Siapkan `.env` production

Salin file contoh:

```bash
cd /www/wwwroot/erpos.mitrasejatiberkah.com
cp .env.aapanel.prod.example .env
```

Isi dengan nilai production final.

Contoh `.env` final production dengan managed DB:

```env
APP_NAME=ERPOS
APP_ENV=production
APP_DEBUG=false
APP_URL=https://erpos.mitrasejatiberkah.com
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id
APP_FALLBACK_LOCALE=en
APP_SLOW_QUERY_THRESHOLD_MS=500
APP_SLOW_QUERY_LOG_SQLITE=false

APP_KEY=

LOG_CHANNEL=stack
LOG_STACK=single,alerts
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=your-managed-db-endpoint.amazonaws.com
DB_PORT=3306
DB_DATABASE=erpos
DB_USERNAME=erpos_app
DB_PASSWORD=replace_me

CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=120

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=public

MAIL_MAILER=smtp
MAIL_HOST=mail.your-domain.com
MAIL_PORT=587
MAIL_USERNAME=noreply@mitrasejatiberkah.com
MAIL_PASSWORD=replace_me
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@mitrasejatiberkah.com
MAIL_FROM_NAME="ERPOS"

VITE_APP_NAME="${APP_NAME}"
```

Catatan penting:
- walaupun database memakai managed DB, Laravel tetap memakai:
  - `DB_CONNECTION=mysql`
- jangan isi `DB_HOST=127.0.0.1` untuk production ini

## 14. Install dependency backend

Gunakan Composer yang kompatibel dengan PHP 8.3.

Kalau `composer2` belum ada di server, install dulu Composer versi baru.
Setelah itu baru jalankan:

```bash
cd /www/wwwroot/erpos.mitrasejatiberkah.com
COMPOSER_ALLOW_SUPERUSER=1 composer2 install --no-dev --optimize-autoloader
php artisan key:generate --force
```

Kalau servermu hanya punya binary `composer` yang sudah versi baru, command bisa disesuaikan.

## 15. Build asset frontend

Pastikan Node.js sudah cukup baru:
- `22.x`
- atau minimal `20.19+`

```bash
cd /www/wwwroot/erpos.mitrasejatiberkah.com
npm install
npm run build
```

## 16. Inisialisasi database production

Ada 2 skenario.

### Skenario A: bootstrap production baru
Kalau production baru dan kamu ingin isi database awal:

```bash
cd /www/wwwroot/erpos.mitrasejatiberkah.com
CACHE_STORE=file php artisan migrate --force
```

Kalau memang perlu data bootstrap dari dump SQL:

```bash
mysql -h YOUR_MANAGED_DB_HOST -P 3306 -u YOUR_DB_USER -p YOUR_DB_NAME < database/sql/tespgpos_mysql_prod_bootstrap.sql
CACHE_STORE=file php artisan migrate --force
```

### Skenario B: import database production lama
Kalau kamu memindahkan DB production lama:

```bash
mysql -h YOUR_MANAGED_DB_HOST -P 3306 -u YOUR_DB_USER -p YOUR_DB_NAME < /path/to/backup-prod.sql
cd /www/wwwroot/erpos.mitrasejatiberkah.com
CACHE_STORE=file php artisan migrate --force
```

Catatan:
- command `mysql ... < backup.sql` dijalankan dari terminal Linux app server
- kalau kamu tidak nyaman import lewat terminal, alternatifnya bisa pakai client SQL desktop dari komputer lokal
- untuk file backup besar, terminal biasanya lebih stabil daripada phpMyAdmin

Jangan jalankan di production:

```bash
php artisan migrate:fresh --seed
```

## 17. Link storage dan cache production

```bash
cd /www/wwwroot/erpos.mitrasejatiberkah.com
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
```

Kalau pernah ada masalah permission:

```bash
chown -R www:www storage bootstrap/cache
find storage -type d -exec chmod 775 {} \;
find storage -type f -exec chmod 664 {} \;
find bootstrap/cache -type d -exec chmod 775 {} \;
find bootstrap/cache -type f -exec chmod 664 {} \;
```

## 18. Scheduler dan queue production

### Scheduler
Di `Cron` aaPanel, jalankan tiap menit:

```bash
cd /www/wwwroot/erpos.mitrasejatiberkah.com && php artisan schedule:run >> /dev/null 2>&1
```

### Queue worker
Buat worker yang berjalan terus-menerus.

Untuk jalur paling sederhana:

```bash
cd /www/wwwroot/erpos.mitrasejatiberkah.com && php artisan queue:work --stop-when-empty --tries=1 >> /dev/null 2>&1
```

Tapi untuk production yang dipakai harian, lebih baik worker dibuat sebagai process manager, bukan sekadar cron sekali jalan.

Prinsipnya:
- `schedule:run` = cocok di cron
- `queue:work` = lebih cocok dijaga sebagai proses yang hidup terus

## 19. SSL server dan Cloudflare

1. issue SSL Let's Encrypt di aaPanel untuk:
   - `erpos.mitrasejatiberkah.com`
2. pastikan site bisa diakses via HTTPS
3. setelah itu, di Cloudflare ubah:
   - `DNS only` -> `Proxied` bila memang ingin diproxy
4. set `SSL/TLS mode = Full (strict)`

## 20. Smoke test production

Jalankan jalur yang aman untuk server production:

```bash
cd /www/wwwroot/erpos.mitrasejatiberkah.com
php artisan app:deploy-check --skip-ops
```

Kalau ingin cek lebih lengkap:

```bash
php artisan app:deploy-check
```

Catatan:
- kalau server install dependency dengan `--no-dev`, `artisan test` memang bisa tidak tersedia
- `app:deploy-check` sudah disiapkan untuk fallback ke jalur smoke test production yang cocok

## 21. Backup yang wajib dipikirkan

Karena DB dipisah, backup dibagi dua.

### Backup file app
Backup minimal:
- folder app
- `.env`
- `storage/app`
- `public/storage`
- konfigurasi panel yang penting

### Backup database
Backup minimal:
- snapshot / backup otomatis dari Lightsail Managed Database
- dump SQL manual berkala untuk berjaga-jaga migrasi atau rollback

## 22. Update aplikasi production

Urutan aman:

```bash
cd /www/wwwroot/erpos.mitrasejatiberkah.com
git pull origin master
COMPOSER_ALLOW_SUPERUSER=1 composer2 install --no-dev --optimize-autoloader
npm install
npm run build
CACHE_STORE=file php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
php artisan app:deploy-check --skip-ops
```

Kalau kamu ingin deploy lebih deterministik dan `package-lock.json` selalu sinkron, `npm ci` lebih baik daripada `npm install`.

## 23. Rollback singkat kalau update gagal

1. rollback code ke commit sebelumnya
2. restore file app kalau perlu
3. restore DB dari snapshot / dump kalau ada perubahan schema/data yang bermasalah
4. jalankan lagi:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
```

## 24. Checklist final go-live production

- app server stabil
- managed DB bisa diakses dari app server
- `APP_KEY` benar
- `APP_DEBUG=false`
- domain `erpos.mitrasejatiberkah.com` sudah aktif
- SSL server aktif
- mode SSL Cloudflare = `Full (strict)`
- scheduler aktif
- queue worker aktif
- smoke test production lulus
- backup file app siap
- snapshot managed DB aktif
- uji login admin dan user lulus
- transaksi inti, print, PDF, Excel, dan piutang sudah diuji

## 25. Dokumen pendamping

- `docs/DEPLOY_AAPANEL.md` untuk `teserpos`
- `docs/UAT_AAPANEL_POST_DEPLOY.md` untuk UAT env tes
- `docs/BACKUP_OPS_HEALTH_README.md`
- `docs/RECOVERY_SOP.md`
- `docs/GO_LIVE_RUNBOOK.md`
