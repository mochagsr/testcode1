# cPanel Terminal Commands

Dokumen ini berisi urutan command yang bisa langsung kamu copy-paste.

Ganti placeholder:

- `CPANEL_USERNAME`
- `REPO_URL`
- `REPO_BRANCH`
- `PROJECT_PATH`
- `TES_DOMAIN`
- `PROD_DOMAIN`

Contoh:

- `PROJECT_PATH=/home/CPANEL_USERNAME/repositories/tespgpos`

## A. Clone pertama kali

```bash
cd /home/CPANEL_USERNAME
git clone REPO_URL repositories/tespgpos
cd repositories/tespgpos
git checkout REPO_BRANCH
composer install --no-dev --optimize-autoloader
```

## B. Deploy `tes`

### 1. Siapkan env

```bash
cd /home/CPANEL_USERNAME/repositories/tespgpos
cp .env.cpanel.test.example .env
php artisan key:generate
```

Lalu edit `.env` dengan File Manager atau terminal editor.

### 2. Import database `tes`

Jika import via terminal:

```bash
mysql -u TES_DB_USER -p TES_DB_NAME < database/sql/tespgpos_mysql_test_snapshot.sql
```

Lalu:

```bash
cd /home/CPANEL_USERNAME/repositories/tespgpos
php artisan db:seed --force
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
```

## C. Deploy `prod`

### 1. Siapkan env

```bash
cd /home/CPANEL_USERNAME/repositories/tespgpos
cp .env.cpanel.prod.example .env
php artisan key:generate
```

### 2. Inisialisasi database `prod`

Pilihan aman:

```bash
cd /home/CPANEL_USERNAME/repositories/tespgpos
php artisan migrate --force
php artisan db:seed --force
```

Atau import bootstrap:

```bash
mysql -u PROD_DB_USER -p PROD_DB_NAME < database/sql/tespgpos_mysql_prod_bootstrap.sql
cd /home/CPANEL_USERNAME/repositories/tespgpos
php artisan db:seed --force
```

### 3. Optimasi `prod`

```bash
cd /home/CPANEL_USERNAME/repositories/tespgpos
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
```

## D. Update dari GitHub

```bash
cd /home/CPANEL_USERNAME/repositories/tespgpos
git pull origin master
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
```

### Update kecil

```bash
cd /home/CPANEL_USERNAME/repositories/tespgpos
git pull origin master
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Update dengan migration

```bash
cd /home/CPANEL_USERNAME/repositories/tespgpos
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
cd /home/CPANEL_USERNAME/repositories/tespgpos
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

## E. Cron cPanel

### Scheduler

```bash
/usr/local/bin/php /home/CPANEL_USERNAME/repositories/tespgpos/artisan schedule:run >> /dev/null 2>&1
```

### Queue

```bash
/usr/local/bin/php /home/CPANEL_USERNAME/repositories/tespgpos/artisan queue:work --stop-when-empty --tries=1 >> /dev/null 2>&1
```

## F. Backup dan restore test

### Backup database

```bash
cd /home/CPANEL_USERNAME/repositories/tespgpos
php artisan app:db-backup --gzip
```

### Restore drill

```bash
cd /home/CPANEL_USERNAME/repositories/tespgpos
php artisan app:db-restore-test
```

### Smoke test

```bash
cd /home/CPANEL_USERNAME/repositories/tespgpos
php artisan app:smoke-test
```

## G. Regenerate SQL `tes` dari lokal

Command ini dijalankan di mesin lokal sebelum upload/push hasil snapshot:

```bash
php artisan app:sqlite-to-mysql-snapshot
```

## H. Regenerate SQL `prod` bootstrap dari lokal

Command ini dijalankan di mesin lokal sebelum upload/push hasil bootstrap production:

```bash
php artisan app:mysql-prod-bootstrap
```
