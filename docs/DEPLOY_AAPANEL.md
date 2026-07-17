# Deploy ERPOS di aaPanel v8.0.1 (Proxmox + Cloudflare Tunnel)

Dokumen ini khusus untuk deployment production `erpos.kiananreho.com`.

Fokus dokumen ini:
- app berjalan di server `aaPanel` di dalam `Proxmox` (jaringan lokal)
- panel yang dipakai adalah `aaPanel v8.0.1`
- database berada di server MySQL terpisah (juga di `Proxmox`)
- domain diregistrasi di `Domainesia`, DNS + akses publik lewat `Cloudflare Tunnel`

Istilah menu yang dipakai di dokumen ini mengikuti aaPanel `v8.0.1`:
- `Website`
- `App Store`
- `Databases`
- `Cron`
- `Terminal`
- `Files`

Catatan:
- file env contoh yang dipakai di dokumen ini:
  - `.env.aapanel.prod.example`

## 0. Ringkasan arsitektur erpos

- Domain:
  - `erpos.kiananreho.com`
- Registrar domain:
  - `Domainesia`
- DNS + akses publik:
  - `Cloudflare Tunnel` (tidak perlu buka port 80/443 di router)
- Panel:
  - `aaPanel`
- Host virtualisasi:
  - `Proxmox`
- App server (VM/LXC aaPanel):
  - IP lokal `192.168.1.7`
- Database server (VM/LXC MySQL + phpMyAdmin):
  - IP lokal `192.168.1.8`
- Connector Cloudflare (LXC cloudflared, sudah ada):
  - IP lokal `192.168.1.11`
- Folder app:
  - `/www/wwwroot/erpos.kiananreho.com`
- Running directory:
  - `/www/wwwroot/erpos.kiananreho.com/public`

Catatan penting: `cloudflared` TIDAK berjalan di server aaPanel. Ia berjalan di LXC terpisah `192.168.1.11`. Karena itu Public Hostname harus mengarah ke IP aaPanel (`http://192.168.1.7:80`), bukan `localhost`.

Diagram singkat alur request:

```
Browser user
   |
   v
Cloudflare (domain dari Domainesia, nameserver diarahkan ke Cloudflare)
   |  (Cloudflare Tunnel, koneksi keluar dari LXC connector)
   v
cloudflared @ 192.168.1.11  ->  aaPanel / Nginx @ 192.168.1.7:80  ->  app erpos
                                                                        |
                                                                        v
                                                       MySQL @ 192.168.1.8:3306
```

## 1. Dokumen pendamping

- UAT setelah deploy:
  - `docs/UAT_AAPANEL_POST_DEPLOY.md`
- Backup / ops:
  - `docs/BACKUP_OPS_HEALTH_README.md`
- Recovery:
  - `docs/RECOVERY_SOP.md`

## 2. Persiapan Proxmox, aaPanel, dan DB server

### 2.1. Dua VM/LXC di Proxmox

Siapkan dua guest di `Proxmox`:
- App server (aaPanel):
  - `Ubuntu 24.04 LTS`
  - IP statik lokal `192.168.1.7`
  - install `aaPanel`
- DB server (MySQL + phpMyAdmin):
  - `Ubuntu 24.04 LTS`
  - IP statik lokal `192.168.1.8`
  - install MySQL 8.x (boleh lewat aaPanel juga) + phpMyAdmin

Set IP statik lewat konfigurasi Proxmox / netplan supaya `192.168.1.7` dan `192.168.1.8` tidak berubah.

### 2.2. Port yang perlu dibuka (LAN saja)

Karena akses publik lewat `Cloudflare Tunnel`, kamu TIDAK perlu buka port `80`/`443` ke internet / router.

Cukup buka di jaringan lokal:
- App server `192.168.1.7`:
  - `22` (SSH, dari LAN)
  - port panel aaPanel, misalnya `21218` (dari LAN)
  - `80` harus bisa diakses dari LXC connector `192.168.1.11` (bukan cuma localhost, karena cloudflared jalan di mesin lain)
- DB server `192.168.1.8`:
  - `3306` hanya dari `192.168.1.7` (app server)
  - port phpMyAdmin / panel (dari LAN)
- LXC connector `192.168.1.11`:
  - tidak perlu buka port masuk; cloudflared hanya konek keluar ke Cloudflare

## 3. Setup Cloudflare Tunnel (dashboard / web)

Connector `cloudflared` SUDAH berjalan di LXC terpisah `192.168.1.11`. Jadi kamu TIDAK perlu install cloudflared apa pun di server aaPanel. Yang perlu dilakukan cuma menambah Public Hostname baru pada tunnel yang sudah ada, dan mengarahkannya ke IP aaPanel.

Prasyarat:
- domain `kiananreho.com` sudah ada di Cloudflare, nameserver di `Domainesia` sudah diarahkan ke `Cloudflare`
- LXC `192.168.1.11` sudah punya tunnel yang berstatus `HEALTHY` di dashboard
- LXC `192.168.1.11` bisa menjangkau `192.168.1.7:80` (satu jaringan LAN)

### 3.1. Pastikan connector yang sudah ada sehat

Di `Cloudflare Zero Trust` → `Networks` → `Tunnels`, cek tunnel yang dijalankan LXC `192.168.1.11` berstatus `HEALTHY`. Kalau perlu, cek juga dari LXC-nya:

```bash
# dijalankan di LXC 192.168.1.11, bukan di aaPanel
sudo systemctl status cloudflared
```

Kalau belum punya tunnel sama sekali, buat dulu tunnel dan jalankan connector-nya di LXC `192.168.1.11` (bukan di aaPanel) lewat perintah `sudo cloudflared service install <TOKEN>` dari wizard dashboard.

### 3.2. Tambahkan Public Hostname ke IP aaPanel

Buka tunnel tersebut → tab `Public Hostname` → `Add a public hostname`:
- `Subdomain`: `erpos`
- `Domain`: `kiananreho.com`
- `Path`: kosongkan
- `Type`: `HTTP`
- `URL`: `192.168.1.7:80`  ← IP aaPanel, BUKAN `localhost`

Simpan. Cloudflare otomatis membuat DNS record untuk `erpos.kiananreho.com` yang mengarah ke tunnel — tidak perlu menambah DNS record manual.

Kenapa `192.168.1.7:80` dan bukan `localhost:80`: cloudflared berjalan di LXC `192.168.1.11`, jadi `localhost` bagi dia adalah LXC itu sendiri, bukan aaPanel. Service URL harus menunjuk ke IP mesin tempat Nginx/aaPanel benar-benar berjalan.

Catatan:
- di aaPanel, buat `Website` untuk `erpos.kiananreho.com` seperti biasa (Nginx dengar di port `80`)
- karena TLS di-terminate Cloudflare, `APP_URL` tetap `https://...`; di tunnel cukup `Type` `HTTP` ke `192.168.1.7:80`
- kalau app perlu jalur HTTPS internal, ganti `Type` jadi `HTTPS` dan aktifkan `No TLS Verify` di `Additional application settings` tunnel
- semua perubahan hostname/ingress dilakukan dari dashboard; tidak ada file config di server aaPanel yang perlu diedit

## 4. Env yang dipakai

Salin:

```bash
cd /www/wwwroot/erpos.kiananreho.com
cp .env.aapanel.prod.example .env
```

Lalu isi nilai final seperti (perhatikan `DB_HOST` menunjuk ke DB server terpisah `192.168.1.8`):

```env
APP_NAME=ERPOS
APP_ENV=production
APP_DEBUG=false
APP_URL=https://erpos.kiananreho.com

DB_CONNECTION=mysql
DB_HOST=192.168.1.8
DB_PORT=3306
DB_DATABASE=sql_erpos_kiananreho_com
DB_USERNAME=sql_erpos_kiananreho_com
DB_PASSWORD=isi_password_database
```

## 5. Buka akses MySQL dari app server

Karena database ada di host berbeda (`192.168.1.8`) dan app di `192.168.1.7`, user MySQL harus boleh konek dari IP app server.

Di DB server `192.168.1.8` (phpMyAdmin atau CLI), buat/beri hak user untuk host app:

```sql
CREATE DATABASE IF NOT EXISTS sql_erpos_kiananreho_com
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'sql_erpos_kiananreho_com'@'192.168.1.7'
  IDENTIFIED BY 'isi_password_database';

GRANT ALL PRIVILEGES ON sql_erpos_kiananreho_com.*
  TO 'sql_erpos_kiananreho_com'@'192.168.1.7';

FLUSH PRIVILEGES;
```

Pastikan juga:
- `bind-address` MySQL di `192.168.1.8` tidak dikunci ke `127.0.0.1` (set `0.0.0.0` atau `192.168.1.8`)
- firewall DB server hanya mengizinkan `3306` dari `192.168.1.7`

Tes koneksi dari app server `192.168.1.7`:

```bash
mysql -h 192.168.1.8 -P 3306 -u sql_erpos_kiananreho_com -p
```

## 6. Jalur deploy

Jalankan di app server `192.168.1.7`:

```bash
cd /www/wwwroot
mkdir -p erpos.kiananreho.com
cd /www/wwwroot/erpos.kiananreho.com
git clone https://github.com/mochagsr/testcode1.git .
cp .env.aapanel.prod.example .env
composer2 install --no-dev --optimize-autoloader
php artisan key:generate
npm install
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan app:about-updates-refresh
php artisan view:cache
```

## 7. Scheduler dan queue

Cron scheduler:

```bash
cd /www/wwwroot/erpos.kiananreho.com && php artisan schedule:run >> /dev/null 2>&1
```

Queue worker:

```bash
cd /www/wwwroot/erpos.kiananreho.com && php artisan queue:work --stop-when-empty --tries=1 >> /dev/null 2>&1
```

## 8. Smoke test

Di server production-style dengan `composer --no-dev`, jalankan:

```bash
cd /www/wwwroot/erpos.kiananreho.com
php artisan app:deploy-check --skip-ops
```

Kalau mau cek ops juga:

```bash
php artisan app:deploy-check
```

Selain itu, cek dari luar bahwa tunnel hidup:
- buka `https://erpos.kiananreho.com` dari jaringan lain (mis. data seluler)
- pastikan `systemctl status cloudflared` di LXC connector `192.168.1.11` masih `active`
- kalau situs tak bisa diakses tapi app sehat, tes dari LXC `192.168.1.11`: `curl -I http://192.168.1.7:80` harus dapat respons dari Nginx aaPanel

## 9. Pola update aman

Command update singkat:

```bash
cd /www/wwwroot/erpos.kiananreho.com
git pull origin master
php artisan migrate --force
php artisan optimize:clear
php artisan app:about-updates-refresh
php artisan view:cache
```

Catatan:
- `app:about-updates-refresh` memperbarui daftar commit di `Sistem > About`
- jalankan command ini setiap selesai `git pull`

## 10. Catatan penting

- app (`192.168.1.7`) dan database (`192.168.1.8`) berada di VM/LXC berbeda di `Proxmox`, jadi `DB_HOST` wajib `192.168.1.8`, bukan `127.0.0.1`
- `cloudflared` berjalan di LXC tersendiri (`192.168.1.11`), bukan di aaPanel, jadi Public Hostname menunjuk `192.168.1.7:80`, bukan `localhost:80`
- akses publik memakai `Cloudflare Tunnel`; tidak ada port `80`/`443` yang dibuka ke internet
- domain diregistrasi di `Domainesia`, tetapi DNS + tunnel dikelola dari `Cloudflare`
- kalau `cloudflared` di `192.168.1.11` mati, situs tidak bisa diakses dari internet walau app-nya sehat — pantau service `cloudflared` di LXC itu
- backup database ambil dari DB server `192.168.1.8`; ikuti `docs/BACKUP_OPS_HEALTH_README.md`
