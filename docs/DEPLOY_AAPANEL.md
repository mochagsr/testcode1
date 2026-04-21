# Deploy ERPOS di aaPanel v8.0.1 untuk TESERPOS

Dokumen ini khusus untuk environment `teserpos.mitrasejatiberkah.com`.

Fokus dokumen ini:
- app berjalan di server `AWS Lightsail`
- panel yang dipakai adalah `aaPanel v8.0.1`
- database `tes` tetap berada di server aaPanel / local DB
- domain dan DNS bisa dikelola lewat `Cloudflare`

Kalau kamu ingin deploy `erpos.mitrasejatiberkah.com` untuk production dengan database terpisah di `AWS Lightsail Managed Database`, gunakan dokumen ini:
- `docs/DEPLOY_AAPANEL_PROD_AWS_MANAGED_DB.md`

Istilah menu yang dipakai di dokumen ini mengikuti aaPanel `v8.0.1`:
- `Website`
- `App Store`
- `Databases`
- `Cron`
- `Terminal`
- `Files`

Catatan:
- dokumen lama `DEPLOY_AAPANEL.md` sekarang difokuskan untuk `teserpos`
- file env contoh yang dipakai di dokumen ini:
  - `.env.aapanel.test.example`
- untuk jalur production terpisah, gunakan env contoh:
  - `.env.aapanel.prod.example`

## 0. Ringkasan arsitektur teserpos

- Domain tes:
  - `teserpos.mitrasejatiberkah.com`
- Panel:
  - `aaPanel`
- App server:
  - `AWS Lightsail Instance`
- Database tes:
  - database lokal di server aaPanel
- Folder app:
  - `/www/wwwroot/teserpos.mitrasejatiberkah.com`
- Running directory:
  - `/www/wwwroot/teserpos.mitrasejatiberkah.com/public`

## 1. Kapan dokumen ini dipakai

Gunakan dokumen ini kalau:
- kamu ingin deploy env `tes`
- kamu ingin uji update dulu sebelum ke production
- kamu masih ingin database `tes` menyatu dengan server aaPanel

Jangan pakai dokumen ini untuk `erpos` production kalau production nanti memakai managed DB terpisah.

## 2. Dokumen pendamping

- UAT tes setelah deploy:
  - `docs/UAT_AAPANEL_POST_DEPLOY.md`
- Backup / ops:
  - `docs/BACKUP_OPS_HEALTH_README.md`
- Recovery:
  - `docs/RECOVERY_SOP.md`
- Production aaPanel + managed DB:
  - `docs/DEPLOY_AAPANEL_PROD_AWS_MANAGED_DB.md`

## 3. Persiapan Lightsail dan aaPanel

Ikuti alur dasar yang sama seperti sebelumnya:
- buat instance `Ubuntu 24.04 LTS`
- attach `Static IP`
- buka port:
  - `22`
  - `80`
  - `443`
  - port panel aaPanel kamu, misalnya `21218`
- install `aaPanel`
- setup DNS `Cloudflare`

Untuk setup rinci jaringan, DNS, dan aaPanel, kamu tetap bisa mengikuti section teknis di dokumen lama ini mulai dari:
- `## 0. Persiapan VPS di AWS Lightsail`
- `### 0.7. Setup DNS di Cloudflare`
- `### 0.8. Cek propagasi DNS`

## 4. Env yang dipakai

Untuk `teserpos`, salin:

```bash
cd /www/wwwroot/teserpos.mitrasejatiberkah.com
cp .env.aapanel.test.example .env
```

Lalu isi nilai final seperti:

```env
APP_NAME=TESERPOS
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://teserpos.mitrasejatiberkah.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sql_teserpos_mitrasejatiberkah_com
DB_USERNAME=sql_teserpos_mitrasejatiberkah_com
DB_PASSWORD=isi_password_database_tes
```

## 5. Jalur deploy yang disarankan untuk teserpos

```bash
cd /www/wwwroot
mkdir -p teserpos.mitrasejatiberkah.com
cd /www/wwwroot/teserpos.mitrasejatiberkah.com
git clone https://github.com/mochagsr/testcode1.git .
cp .env.aapanel.test.example .env
composer2 install --no-dev --optimize-autoloader
php artisan key:generate
npm install
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
```

## 6. Scheduler dan queue tes

Cron scheduler:

```bash
cd /www/wwwroot/teserpos.mitrasejatiberkah.com && php artisan schedule:run >> /dev/null 2>&1
```

Queue worker:

```bash
cd /www/wwwroot/teserpos.mitrasejatiberkah.com && php artisan queue:work --stop-when-empty --tries=1 >> /dev/null 2>&1
```

## 7. Smoke test teserpos

Di server production-style dengan `composer --no-dev`, jalankan:

```bash
cd /www/wwwroot/teserpos.mitrasejatiberkah.com
php artisan app:deploy-check --skip-ops
```

Kalau mau cek ops juga:

```bash
php artisan app:deploy-check
```

## 8. Pola update aman

1. update `teserpos` dulu
2. jalankan smoke test
3. lakukan UAT user
4. baru lanjut ke production `erpos`

## 9. Catatan penting

- `teserpos` boleh memakai database lokal supaya setup lebih sederhana
- jangan jadikan `teserpos` acuan arsitektur production kalau production memakai managed DB
- untuk production, selalu ikuti dokumen khusus:
  - `docs/DEPLOY_AAPANEL_PROD_AWS_MANAGED_DB.md`
