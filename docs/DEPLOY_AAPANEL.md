# Deploy PgPOS ERP di aaPanel v8.0.1

Dokumen ini disiapkan untuk deploy aplikasi ke server Linux yang memakai `aaPanel v8.0.1`.

Istilah menu yang dipakai di dokumen ini mengikuti aaPanel `v8.0.1`:
- `Website`
- `App Store`
- `Databases`
- `Cron`
- `Terminal`
- `Files`

Catatan:
- di dokumentasi resmi aaPanel, website PHP kadang disebut `PHP Project`
- di panel harian kamu biasanya tetap bekerja lewat menu `Website`
- untuk env aaPanel, dokumen ini sekarang memakai file contoh:
  - `.env.aapanel.test.example`
  - `.env.aapanel.prod.example`

## Akun default setelah seed
- Admin
  - username: `admin`
  - email: `admin@pgpos.local`
  - password: `@Passwordadmin123#`
- User
  - username: `user`
  - email: `user@pgpos.local`
  - password: `@Passworduser123#`

Login app mendukung:
- username + password
- atau email + password

Kalau sesudah deploy kamu masih login dengan akun default, segera simpan password final internal perusahaan di tempat aman.

## 1. Stack aplikasi

- PHP `8.3`
- Laravel `12`
- MySQL / MariaDB
- Queue driver: `database`
- Session driver: `database`
- Cache store: `database`
- PDF export: `barryvdh/laravel-dompdf`
- Excel export: `phpoffice/phpspreadsheet`

## 2. Minimum server yang layak

Minimum:
- `2 vCPU`
- `2 GB RAM`
- `40 GB SSD`

Rekomendasi:
- `4 vCPU`
- `4-8 GB RAM`
- `60-100 GB NVMe`

## 3. Paket yang perlu di-install di aaPanel v8.0.1

Buka menu `App Store`, lalu install:

1. `Nginx`
2. `MySQL` atau `MariaDB`
3. `PHP 8.3`
4. `phpMyAdmin` kalau mau import SQL via browser

Tambahan yang saya sarankan:
5. `Redis` opsional
6. `Node.js` kalau mau build langsung di server

## 4. Extension PHP yang wajib aktif

Di aaPanel `v8.0.1`:
- buka `App Store`
- buka `PHP 8.3`
- masuk `Extensions`

Aktifkan:
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

Opsional tapi bagus:
- `intl`
- `opcache`

## 5. Software tambahan di server

Jalankan via `Terminal`:

```bash
apt update
apt install -y git unzip curl supervisor nodejs npm
```

Kalau `composer` belum ada:

```bash
cd /tmp
curl -sS https://getcomposer.org/installer -o composer-setup.php
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
composer --version
```

## 6. Struktur folder yang disarankan

Contoh yang dipakai di dokumen ini:
- env `tes`:
  - website: `teserpos.mitrasejatiberkah.com`
  - folder: `/www/wwwroot/teserpos.mitrasejatiberkah.com`
- env `prod`:
  - website: `erpos.mitrasejaitberkah.com`
  - folder: `/www/wwwroot/erpos.mitrasejaitberkah.com`

Running directory website harus ke:
- `/www/wwwroot/teserpos.mitrasejatiberkah.com/public`
- `/www/wwwroot/erpos.mitrasejaitberkah.com/public`

## 6.1. Setup subdomain: DomaiNesia + VPS aaPanel di IDCloudHost

Bagian ini penting kalau:
- domain kamu ada di `DomaiNesia`
- VPS Linux + `aaPanel` ada di `IDCloudHost`

Contoh target:
- `teserpos.mitrasejatiberkah.com` untuk env `tes`
- `erpos.mitrasejaitberkah.com` untuk env `prod`

Alurnya:
1. ambil IP public VPS dari `IDCloudHost`
2. arahkan subdomain ke IP VPS itu
3. kalau DNS dikelola di `DomaiNesia`, buat record di `DomaiNesia`
4. kalau DNS dikelola di `Cloudflare`, buat record di `Cloudflare`
5. baru buat site di `aaPanel`

### 6.1A. Ambil IP public VPS di IDCloudHost

Di panel `IDCloudHost`, cari detail VM/VPS kamu lalu catat:
- `Public IPv4`

Contoh:
- `103.123.45.67`

IP ini yang nanti dipakai di record DNS domain.

### 6.1B. Opsi DNS 1 - Buat record subdomain di DomaiNesia langsung

Di panel `DomaiNesia`, biasanya alurnya:
1. buka `Domains`
2. pilih domain kamu
3. buka `DNS Management`
4. tambah `A Record`

Contoh record:

#### Untuk env tes
- `Type`: `A`
- `Name / Host`: `teserpos`
- `Value / Points to`: `103.123.45.67`
- `TTL`: default

#### Untuk env prod
- `Type`: `A`
- `Name / Host`: `erpos`
- `Value / Points to`: `103.123.45.67`
- `TTL`: default

Kalau kamu ingin domain utama dipakai untuk prod:
- `Type`: `A`
- `Name / Host`: `@`
- `Value / Points to`: `103.123.45.67`

Kalau butuh `www`:
- `Type`: `CNAME`
- `Name / Host`: `www`
- `Value / Points to`: `erpos.mitrasejaitberkah.com`

### 6.1C. Opsi DNS 2 - Buat record subdomain di Cloudflare

Pakai bagian ini kalau:
- nameserver domain kamu sudah diarahkan ke `Cloudflare`
- jadi pengelolaan DNS **bukan lagi** di panel DNS DomaiNesia

Langkah di `Cloudflare`:
1. buka domain kamu di dashboard `Cloudflare`
2. buka menu `DNS`
3. klik `Add record`
4. tambah `A Record`

Contoh record:

#### Untuk env tes
- `Type`: `A`
- `Name`: `teserpos`
- `IPv4 address`: `103.123.45.67`
- `Proxy status`: mulai dari `DNS only`
- `TTL`: `Auto`

#### Untuk env prod
- `Type`: `A`
- `Name`: `erpos`
- `IPv4 address`: `103.123.45.67`
- `Proxy status`: mulai dari `DNS only`
- `TTL`: `Auto`

Kalau prod mau pakai root domain:
- `Type`: `A`
- `Name`: `@`
- `IPv4 address`: `103.123.45.67`
- `Proxy status`: `DNS only`

Saran awal:
- pakai `DNS only` dulu saat setup awal
- setelah website dan SSL sudah normal, baru pertimbangkan ubah ke `Proxied`

Catatan Cloudflare:
- kalau pakai `Proxied`, IP origin VPS akan disembunyikan
- tapi saat debugging awal, `DNS only` biasanya lebih sederhana
- kalau nanti pakai SSL Cloudflare, pastikan mode SSL tidak bentrok dengan SSL di server

### 6.1D. Tunggu propagasi DNS

Biasanya:
- cepat: `1-10 menit`
- normal: `30 menit - 2 jam`
- kadang bisa lebih lama tergantung resolver

Cara cek di server:

```bash
ping teserpos.mitrasejatiberkah.com
ping erpos.mitrasejaitberkah.com
```

Atau cek dengan:

```bash
nslookup teserpos.mitrasejatiberkah.com
nslookup erpos.mitrasejaitberkah.com
```

Kalau hasil IP sudah sama dengan IP VPS `IDCloudHost`, berarti DNS sudah benar.

### 6.1E. Baru lanjut buat site di aaPanel

Setelah DNS benar:
- buat site `teserpos.mitrasejatiberkah.com` di aaPanel untuk env `tes`
- buat site `erpos.mitrasejaitberkah.com` di aaPanel untuk env `prod`

Catatan penting:
- jangan buat site dulu lalu berharap subdomain otomatis hidup
- DNS di `DomaiNesia` dan site di `aaPanel` adalah 2 langkah yang berbeda
- `aaPanel` hanya melayani domain yang sudah diarahkan ke IP VPS

## 7. Metode deploy yang tersedia di aaPanel v8.0.1

Ada 2 jalur yang layak dipakai:

1. `Terminal + git clone`
2. `Website -> Add site -> Create for Git`

Rekomendasi:
- **deploy pertama**: pakai `Terminal + git clone`
- **deploy berikutnya / update rutin**: boleh tetap manual, atau pakai `Create for Git`

Kenapa `Terminal + git clone` saya jadikan default saat first deploy:
- lebih mudah kontrol folder `tes` dan `prod`
- lebih mudah lihat error `composer`, `artisan`, dan `npm build`
- lebih mudah pastikan `.env`, DB, cache, queue, dan cron benar

Kenapa `Create for Git` tetap layak:
- clone repo jadi lebih cepat dari UI
- cocok kalau tim ingin semua site dibuat langsung dari menu `Website`
- bisa dipakai untuk update `pull` berikutnya dari panel

Catatan penting:
- **`Create for Git` tidak membuat app Laravel langsung siap pakai**
- setelah clone, kamu tetap harus menjalankan:
  - `composer install`
  - isi `.env`
  - `php artisan key:generate`
  - import DB / `migrate`
  - `npm run build`
  - `storage:link`
  - cache
  - cron / queue
- untuk repo ini, branch deploy yang benar adalah:
  - `master`
- jangan pilih `main`
  - karena branch `main` bisa terbaca kosong di server dan membuat file Laravel tidak muncul

## 7A. Opsi A - Rekomendasi: `Terminal + git clone`

Pakai ini kalau:
- ini deploy pertama
- kamu ingin kontrol penuh path project
- kamu ingin minim kejutan saat debug

Alur:
1. buat site di `Website`
2. arahkan path ke folder project
3. clone repo via `Terminal`
4. lanjut setup Laravel

Contoh:
- `teserpos` -> `/www/wwwroot/teserpos.mitrasejatiberkah.com`
- `erpos` -> `/www/wwwroot/erpos.mitrasejaitberkah.com`

## 7B. Opsi B - `Website -> Add site -> Create for Git`

Pakai ini kalau:
- kamu nyaman membuat site sekaligus clone repo dari UI aaPanel
- kamu tetap siap masuk `Terminal` untuk langkah Laravel sesudah clone

Langkah di aaPanel `v8.0.1`:
1. buka `Website`
2. klik `Add site`
3. pilih opsi `Create for Git`
4. isi:
   - `Domain`
   - `Project path`
   - `PHP Version` = `PHP 8.3`
   - `Git Repository URL`
   - branch = `master`
5. simpan

Catatan autentikasi Git:
- kalau repo GitHub kamu `public`:
  - **tidak perlu** SSH key
  - cukup pakai URL `HTTPS`
  - contoh:
    - `https://github.com/mochagsr/testcode1.git`
- kalau repo GitHub kamu `private`:
  - biasanya perlu autentikasi
  - opsi yang umum:
    - `SSH key`
    - atau token/credential lain
- untuk deploy pertama yang sederhana:
  - repo `public` + `HTTPS` adalah jalur paling mudah

### Contoh isi form `Create for Git` di aaPanel

Untuk env `tes` dengan domain:
- `teserpos.mitrasejatiberkah.com`

isi form seperti ini:

- `Domain name`
  - `teserpos.mitrasejatiberkah.com`
- `Description`
  - biarkan auto dari aaPanel
  - atau isi:
    - `teserpos_mitrasejatiberkah_com`
- `Website Path`
  - `/www/wwwroot/teserpos.mitrasejatiberkah.com`
- `FTP`
  - `Not create` 
- `Database`
  - `MySQL`
- `Database settings`
  - boleh pakai nama auto dari aaPanel
  - atau nama yang mudah dikenali
- `Password`
  - pakai password auto-generate dari aaPanel atau isi manual yang kuat
- `PHP version`
  - `PHP-83`
- `Site category`
  - default saja
- `Create html file`
  - **OFF**

Saran praktis:
- `FTP`: pilih `Not create`
  - karena deploy/update akan lewat `Git + Terminal`, bukan upload file manual
- `Database`: pilih `MySQL`
  - karena app ini memang kita siapkan untuk MySQL di server
- `Create html file`: `OFF`
  - karena ini aplikasi Laravel, bukan website HTML statis

Setelah klik `Confirm`, catat data database yang dibuat aaPanel:
- `DB name`
- `DB username`
- `DB password`

Data itu nanti dipakai untuk isi `.env`.

Contoh:
- `DB name`
  - `sql_teserpos_mitrasejatiberkah_com`
- `DB username`
  - `sql_teserpos_mitrasejatiberkah_com`
- `DB password`
  - `password_dari_aapanel`

Lalu di `.env` nanti:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sql_teserpos_mitrasejatiberkah_com
DB_USERNAME=sql_teserpos_mitrasejatiberkah_com
DB_PASSWORD=password_user_database_mysql
```

Untuk env `prod` dengan domain:
- `erpos.mitrasejaitberkah.com`

isi form seperti ini:

- `Domain name`
  - `erpos.mitrasejaitberkah.com`
- `Description`
  - biarkan auto dari aaPanel
  - atau isi:
    - `erpos_mitrasejaitberkah_com`
- `Website Path`
  - `/www/wwwroot/erpos.mitrasejaitberkah.com`
- `FTP`
  - `Not create`
- `Database`
  - `MySQL`
- `Database settings`
  - boleh pakai nama auto dari aaPanel
  - atau nama yang mudah dikenali
- `Password`
  - pakai password auto-generate dari aaPanel atau isi manual yang kuat
- `PHP version`
  - `PHP-83`
- `Site category`
  - default saja
- `Create html file`
  - **OFF**

Contoh hasil database prod:
- `DB name`
  - `sql_erpos_mitrasejaitberkah_com`
- `DB username`
  - `sql_erpos_mitrasejaitberkah_com`
- `DB password`
  - `password_dari_aapanel`

Lalu di `.env` prod nanti:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sql_erpos_mitrasejaitberkah_com
DB_USERNAME=sql_erpos_mitrasejaitberkah_com
DB_PASSWORD=password_user_database_mysql
```

Contoh isian:
- `Domain`:
  - `teserpos.mitrasejatiberkah.com`
- `Project path`:
  - `/www/wwwroot/teserpos.mitrasejatiberkah.com`
- `Git Repository URL`:
  - `https://github.com/mochagsr/testcode1.git`
- `Branch`:
  - `master`

Penting:
- untuk repo ini jangan pilih `main`
- kalau terlanjur ter-clone ke branch `main` dan isi folder hanya `.git` atau `.gitkeep`, pindahkan ke branch `master`

Setelah site berhasil dibuat lewat `Create for Git`, lanjutkan:
1. buka detail site
2. cek dulu apakah source code Laravel sudah benar-benar masuk ke:
   - `/www/wwwroot/teserpos.mitrasejatiberkah.com`
   - atau `/www/wwwroot/erpos.mitrasejaitberkah.com`
3. kalau folder `public` Laravel sudah ada, baru set:
   - `Running directory = public`
4. masuk `Terminal`
5. jalankan langkah Laravel seperti biasa

Catatan:
- `Website Path` tetap folder project utama
- `Running directory` adalah subfolder `public` di dalam project Laravel
- jadi kalau `public` belum ada saat pertama buka detail site, jangan panik
- lanjutkan proses clone dulu, lalu kembali lagi untuk mengarahkan `Running directory`

Contoh untuk env `tes` setelah `Create for Git`:

```bash
cd /www/wwwroot/teserpos.mitrasejatiberkah.com
composer install --no-dev --optimize-autoloader
cp .env.aapanel.test.example .env
php artisan key:generate
mysql -u TES_DB_USER -p TES_DB_NAME < database/sql/tespgpos_mysql_test_snapshot.sql
php artisan db:seed --force
npm install
npm run build
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
php artisan app:db-backup
php artisan app:db-restore-test
php artisan app:smoke-test
```

Contoh untuk env `prod` setelah `Create for Git`:

```bash
cd /www/wwwroot/erpos.mitrasejaitberkah.com
composer install --no-dev --optimize-autoloader
cp .env.aapanel.prod.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
npm install
npm run build
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
php artisan app:db-backup
php artisan app:db-restore-test
php artisan app:smoke-test
```

Ringkasnya:
- `Create for Git` hanya menggantikan langkah clone repo
- semua langkah Laravel setelah itu **tetap wajib**

## 8A. Buat website di aaPanel untuk Opsi A

Ikuti ini kalau kamu memilih `Terminal + git clone`.

Langkah:
1. buka `Website`
2. klik `Add site`
3. isi domain / subdomain
4. pilih `PHP 8.3`
5. set path:
   - `/www/wwwroot/teserpos.mitrasejatiberkah.com`
   - atau `/www/wwwroot/erpos.mitrasejaitberkah.com`
6. simpan

Catatan penting:
- saat site baru dibuat, aaPanel memang langsung membuat folder website
- contoh:
  - `/www/wwwroot/teserpos.mitrasejatiberkah.com`
- folder itu adalah **Website Path / project root**
- ini **belum** berarti subfolder Laravel `public` sudah ada

Jadi pada tahap ini:
- **Website Path** sudah benar
- **Running directory** belum perlu dipaksa dulu kalau source code Laravel belum masuk

Set `Running directory = public` dilakukan **setelah repo berhasil di-clone**, karena barulah folder berikut tersedia:
- `/www/wwwroot/teserpos.mitrasejatiberkah.com/public`
- atau `/www/wwwroot/erpos.mitrasejaitberkah.com/public`

## 9A. Clone code dari GitHub untuk Opsi A

Karena folder website sudah dibuat dulu oleh aaPanel, clone repo harus masuk ke **folder yang sudah ada itu**.

Contoh **SALAH / JANGAN PAKAI**:

```bash
cd /www/wwwroot
git clone https://github.com/mochagsr/testcode1.git teserpos.mitrasejatiberkah.com
```

Kenapa salah:
- folder `teserpos.mitrasejatiberkah.com` sudah dibuat aaPanel
- command itu mencoba membuat folder repo baru dengan nama yang sama
- hasilnya akan error:
  - `destination path ... already exists and is not an empty directory`

Contoh **BENAR / PAKAI YANG INI**:
- masuk dulu ke folder website yang sudah dibuat
- lalu clone ke folder saat ini dengan command lengkap berikut:
  - `git clone https://github.com/mochagsr/testcode1.git .`
- setelah clone, pastikan branch aktif adalah `master`

### Env tes

```bash
cd /www/wwwroot/teserpos.mitrasejatiberkah.com
git clone https://github.com/mochagsr/testcode1.git .
git switch -c master --track origin/master
composer install --no-dev --optimize-autoloader
```

### Env prod

```bash
cd /www/wwwroot/erpos.mitrasejaitberkah.com
git clone https://github.com/mochagsr/testcode1.git .
git switch -c master --track origin/master
composer install --no-dev --optimize-autoloader
```

Kalau folder site masih berisi file default aaPanel seperti `index.html`, hapus dulu sebelum clone:

```bash
cd /www/wwwroot/teserpos.mitrasejatiberkah.com
rm -f index.html index.php
git clone https://github.com/mochagsr/testcode1.git .
git switch -c master --track origin/master
```

Setelah clone selesai:
1. buka kembali menu website di aaPanel
2. masuk ke `Site directory`
3. set `Running directory` ke:
   - `/www/wwwroot/teserpos.mitrasejatiberkah.com/public`
   - atau `/www/wwwroot/erpos.mitrasejaitberkah.com/public`

Ringkas:
- `Website Path` = folder project Laravel
- `Running directory` = folder `public` di dalam project Laravel
- branch deploy yang dipakai = `master`

## 10A. Buat database untuk Opsi A

Di aaPanel `v8.0.1`:
1. buka `Databases`
2. klik `Add DB`
3. buat database kosong untuk env `tes`
4. buat database kosong untuk env `prod`
5. simpan:
   - host
   - db name
   - username
   - password

Biasanya host:
- `127.0.0.1`
- atau `localhost`

Catatan penting:
- pada tahap ini kamu hanya membuat **database kosong**
- **jangan** buat tabel manual di phpMyAdmin
- tabel aplikasi nanti akan masuk lewat salah satu cara ini:
  - import file snapshot / bootstrap SQL
  - atau jalankan `php artisan migrate --force`

## 11A. Siapkan `.env` untuk Opsi A

Cara edit `.env` di aaPanel:

### Opsi 1 - lewat menu `Files`
1. buka `Files`
2. masuk ke folder project:
   - `tes`: `/www/wwwroot/teserpos.mitrasejatiberkah.com`
   - `prod`: `/www/wwwroot/erpos.mitrasejaitberkah.com`
3. kalau file `.env` belum ada:
   - copy dari file contoh
4. klik file `.env`
5. pilih `Edit`
6. ubah nilai yang diperlukan
7. simpan

### Opsi 2 - lewat `Terminal`
Contoh untuk `tes`:

```bash
cd /www/wwwroot/teserpos.mitrasejatiberkah.com
cp .env.aapanel.test.example .env
nano .env
```

Contoh untuk `prod`:

```bash
cd /www/wwwroot/erpos.mitrasejaitberkah.com
cp .env.aapanel.prod.example .env
nano .env
```

Kalau `nano` tidak tersedia, bisa pakai:

```bash
vi .env
```

Catatan penting:
- `.env` adalah file lokal di server
- `.env` **tidak perlu** dan **jangan** dipush ke GitHub
- yang disimpan di repo hanya file contoh:
  - `.env.aapanel.test.example`
  - `.env.aapanel.prod.example`
- karena itu, walaupun `FTP` diset `Not create`, kamu tetap bisa edit `.env` lewat:
  - `Files`
  - atau `Terminal`

### Tes

```bash
cd /www/wwwroot/teserpos.mitrasejatiberkah.com
cp .env.aapanel.test.example .env
php artisan key:generate
```

### Prod

```bash
cd /www/wwwroot/erpos.mitrasejaitberkah.com
cp .env.aapanel.prod.example .env
php artisan key:generate
```

Field minimal yang harus diisi:

```env
APP_NAME="PgPOS ERP"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://teserpos.mitrasejatiberkah.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nama_database
DB_USERNAME=user_database
DB_PASSWORD=password_database

CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
FILESYSTEM_DISK=public
```

### Contoh `.env` final untuk env `tes`

Silakan sesuaikan hanya bagian:
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

```env
APP_NAME="PgPOS ERP TES"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://teserpos.mitrasejatiberkah.com

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sql_teserpos_mitrasejatiberkah_com
DB_USERNAME=sql_teserpos_mitrasejatiberkah_com
DB_PASSWORD=password_dari_aapanel

BROADCAST_CONNECTION=log
CACHE_STORE=database
FILESYSTEM_DISK=public
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=120
```

### Contoh `.env` final untuk env `prod`

Silakan sesuaikan hanya bagian:
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

```env
APP_NAME="PgPOS ERP"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://erpos.mitrasejaitberkah.com

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sql_erpos_mitrasejaitberkah_com
DB_USERNAME=sql_erpos_mitrasejaitberkah_com
DB_PASSWORD=password_dari_aapanel

BROADCAST_CONNECTION=log
CACHE_STORE=database
FILESYSTEM_DISK=public
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=120
```

Setelah selesai edit `.env`, jalankan:

```bash
php artisan optimize:clear
php artisan config:cache
```

Catatan untuk `DB_PASSWORD`:
- isi dengan **password user database MySQL** yang dibuat saat `Add DB`
- **bukan** password login aaPanel
- kalau phpMyAdmin meminta login manual, biasanya password ini juga yang dipakai bersama `DB_USERNAME`

## 12A. Inisialisasi database untuk Opsi A

### Env tes

```bash
mysql -u DB_USER -p DB_NAME < database/sql/tespgpos_mysql_test_snapshot.sql
php artisan db:seed --force
```

### Env prod

Pilihan aman:

```bash
php artisan migrate --force
php artisan db:seed --force
```

Atau import bootstrap:

```bash
mysql -u DB_USER -p DB_NAME < database/sql/tespgpos_mysql_prod_bootstrap.sql
php artisan db:seed --force
```

## 13A. Build asset frontend untuk Opsi A

```bash
npm install
npm run build
```

Catatan urutan:
- langkah ini tetap dikerjakan **lebih dulu**
- sesudah itu baru lanjut ke:
  - `14A` untuk `storage:link` dan cache
  - `16A` untuk backup awal dan smoke test
- jadi urutan normalnya:
  - `13A -> 14A -> 16A`

## 14A. Link storage dan cache production untuk Opsi A

```bash
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
```

## 15A. Scheduler dan queue untuk Opsi A

Di aaPanel `v8.0.1`:
- buka `Cron`
- tambah task baru
- type: `Shell Script`

Scheduler `prod`:

```bash
cd /www/wwwroot/erpos.mitrasejaitberkah.com && php artisan schedule:run >> /dev/null 2>&1
```

Queue `prod`:

```bash
cd /www/wwwroot/erpos.mitrasejaitberkah.com && php artisan queue:work --stop-when-empty --tries=1 >> /dev/null 2>&1
```

## 16A. Backup awal dan smoke test untuk Opsi A

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

Catatan:
- backup awal lebih aman **tanpa `--gzip`**
- restore drill otomatis mencari file `.sql`
- langkah ini dijalankan **setelah**:
  - `13A` build frontend selesai
  - `14A` storage link dan cache selesai

## 17A. Contoh alur lengkap Opsi A

### Env tes

```bash
cd /www/wwwroot
git clone https://github.com/mochagsr/testcode1.git teserpos.mitrasejatiberkah.com
cd /www/wwwroot/teserpos.mitrasejatiberkah.com
composer install --no-dev --optimize-autoloader
cp .env.aapanel.test.example .env
php artisan key:generate
mysql -u TES_DB_USER -p TES_DB_NAME < database/sql/tespgpos_mysql_test_snapshot.sql
php artisan db:seed --force
npm install
npm run build
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
php artisan app:db-backup
php artisan app:db-restore-test
php artisan app:smoke-test
```

### Env prod

```bash
cd /www/wwwroot
git clone https://github.com/mochagsr/testcode1.git erpos.mitrasejaitberkah.com
cd /www/wwwroot/erpos.mitrasejaitberkah.com
composer install --no-dev --optimize-autoloader
cp .env.aapanel.prod.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
npm install
npm run build
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
php artisan app:db-backup
php artisan app:db-restore-test
php artisan app:smoke-test
```

## 8B. Buat website lewat `Create for Git` untuk Opsi B

Ikuti ini kalau kamu memilih `Website -> Add site -> Create for Git`.

Langkah:
1. buka `Website`
2. klik `Add site`
3. pilih `Create for Git`
4. isi:
   - `Domain`
   - `Project path`
   - `PHP Version` = `PHP 8.3`
   - `Git Repository URL`
   - `Branch` = `master`
5. simpan

Contoh:
- `teserpos.mitrasejatiberkah.com`
- path `/www/wwwroot/teserpos.mitrasejatiberkah.com`
- repo `https://github.com/mochagsr/testcode1.git`

## 9B. Cek running directory untuk Opsi B

Setelah site selesai dibuat:
1. buka detail site
2. masuk `Site directory`
3. set `Running directory = public`

## 10B. Buat database untuk Opsi B

Di aaPanel `v8.0.1`:
1. buka `Databases`
2. klik `Add DB`
3. buat database `tes`
4. buat database `prod`
5. simpan host, db name, username, dan password

## 11B. Siapkan `.env` untuk Opsi B

Cara edit `.env` untuk Opsi B sama persis:
- bisa lewat `Files`
- atau lewat `Terminal`
- tetap **tidak perlu** dipush ke Git

### Tes

```bash
cd /www/wwwroot/teserpos.mitrasejatiberkah.com
cp .env.aapanel.test.example .env
php artisan key:generate
```

### Prod

```bash
cd /www/wwwroot/erpos.mitrasejaitberkah.com
cp .env.aapanel.prod.example .env
php artisan key:generate
```

Field minimal yang harus diisi sama seperti `11A`.

Kalau mau langsung praktis, pakai contoh `.env` final di bagian `11A`, lalu sesuaikan:
- `APP_URL`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

## 12B. Inisialisasi database untuk Opsi B

### Env tes

```bash
mysql -u DB_USER -p DB_NAME < database/sql/tespgpos_mysql_test_snapshot.sql
php artisan db:seed --force
```

### Env prod

```bash
php artisan migrate --force
php artisan db:seed --force
```

## 13B. Build asset frontend untuk Opsi B

```bash
npm install
npm run build
```

## 14B. Link storage dan cache production untuk Opsi B

```bash
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
```

## 15B. Scheduler dan queue untuk Opsi B

Scheduler `prod`:

```bash
cd /www/wwwroot/erpos.mitrasejaitberkah.com && php artisan schedule:run >> /dev/null 2>&1
```

Queue `prod`:

```bash
cd /www/wwwroot/erpos.mitrasejaitberkah.com && php artisan queue:work --stop-when-empty --tries=1 >> /dev/null 2>&1
```

## 16B. Backup awal dan smoke test untuk Opsi B

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

## 17B. Contoh alur lengkap Opsi B

### Env tes

```bash
cd /www/wwwroot/teserpos.mitrasejatiberkah.com
composer install --no-dev --optimize-autoloader
cp .env.aapanel.test.example .env
php artisan key:generate
mysql -u TES_DB_USER -p TES_DB_NAME < database/sql/tespgpos_mysql_test_snapshot.sql
php artisan db:seed --force
npm install
npm run build
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
php artisan app:db-backup
php artisan app:db-restore-test
php artisan app:smoke-test
```

### Env prod

```bash
cd /www/wwwroot/erpos.mitrasejaitberkah.com
composer install --no-dev --optimize-autoloader
cp .env.aapanel.prod.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
npm install
npm run build
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
php artisan app:db-backup
php artisan app:db-restore-test
php artisan app:smoke-test
```

## 18. Langkah uji setelah deploy

Uji minimal:
1. login admin
2. buka dashboard
3. buka barang
4. buka customer
5. buka piutang
6. buat 1 faktur
7. print 1 report
8. export PDF
9. export Excel
10. upload logo / file
11. cek queue export

## 19. Update program / upgrade aplikasi

Bagian ini dipakai kalau:
- aplikasi di server sudah jalan
- kamu push update baru ke GitHub
- kamu ingin upgrade code di env `tes` atau `prod`

Aturan utama:
- **jangan upload file satu-satu** lewat `Files`
- update yang benar tetap:
  1. push perubahan ke GitHub
  2. masuk server
  3. jalankan `git pull`
  4. lanjutkan command Laravel sesuai jenis perubahan

### 19A. Jika site dibuat dengan `Create for Git`

Kalau site pertama kali dibuat dari:
- `Website -> Add site -> Create for Git`

maka untuk update berikutnya:
- kamu **tetap bisa** pull update terbaru
- jalur paling aman tetap lewat `Terminal`

Contoh:

```bash
cd /www/wwwroot/erpos.mitrasejaitberkah.com
git pull origin master
```

Sesudah itu lanjutkan:
- update kecil
- update migration
- atau update frontend build

sesuai jenis perubahan di bawah.

### 19B. Jika site dibuat manual dengan `git clone`

Kalau site pertama kali dibuat manual:
- `Website` dibuat biasa
- lalu code di-clone dari `Terminal`

maka pola update juga sama:

```bash
cd /www/wwwroot/erpos.mitrasejaitberkah.com
git pull origin master
```

Jadi kesimpulannya:
- beda metode deploy awal **tidak mengubah** cara update harian
- baik `Create for Git` maupun `git clone`, update tetap paling aman lewat `Terminal`

Jangan upload file satu-satu lewat `Files`.

Pola update yang benar:
1. push perubahan dari lokal ke GitHub
2. masuk server aaPanel
3. `git pull`
4. jalankan command update sesuai jenis perubahan

### Update kecil

```bash
cd /www/wwwroot/erpos.mitrasejaitberkah.com
git pull origin master
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Update dengan migration

```bash
cd /www/wwwroot/erpos.mitrasejaitberkah.com
git pull origin master
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
```

### Update dengan frontend build

```bash
cd /www/wwwroot/erpos.mitrasejaitberkah.com
git pull origin master
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
```

### Pola aman `tes` lalu `prod`

1. update dulu `tes`
2. uji login, transaksi, print, PDF, dan Excel
3. kalau aman, baru update `prod`

### 19C. Rollback singkat kalau update gagal

Kalau sesudah update muncul error dan kamu perlu rollback cepat:

```bash
cd /www/wwwroot/erpos.mitrasejaitberkah.com
git log --oneline -5
git reset --hard <commit-sebelumnya>
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Catatan:
- rollback `hard` hanya aman kalau server deployment memang mengikuti Git dan tidak ada edit manual lokal
- sebelum rollback, idealnya backup DB dulu

## 20. Dokumen pendamping

- `docs/UAT_AAPANEL_POST_DEPLOY.md`
- `docs/IMPORT_MASSAL_PLAYBOOK.md`
- `docs/STRESS_TEST_GUIDE.md`
- `docs/GO_LIVE_RUNBOOK.md`
- `docs/BACKUP_OPS_HEALTH_README.md`

## 21. Saran praktik

- deploy `tes` dulu, jangan langsung `prod`
- pakai DB `tes` dan `prod` terpisah
- backup DB sebelum update
- jangan pakai `APP_DEBUG=true` di server public
- kalau ada 500 error setelah update, jalankan:

```bash
php artisan optimize:clear
```
