# Review `.env` Production

Isi dokumen ini sebelum deploy `prod`. Setelah kamu isi, saya bisa review cepat.

## 1. App

- `APP_NAME=`
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=`
- `APP_TIMEZONE=Asia/Jakarta`
- `APP_LOCALE=id`
- `APP_FALLBACK_LOCALE=en`

## 2. Logging

- `LOG_CHANNEL=stack`
- `LOG_STACK=single,alerts`
- `LOG_LEVEL=error`

## 3. Database

- `DB_CONNECTION=mysql`
- `DB_HOST=localhost`
- `DB_PORT=3306`
- `DB_DATABASE=`
- `DB_USERNAME=`
- `DB_PASSWORD=`

## 4. Session / Cache / Queue

- `CACHE_STORE=database`
- `QUEUE_CONNECTION=database`
- `SESSION_DRIVER=database`
- `SESSION_LIFETIME=120`

## 5. Filesystem

- `FILESYSTEM_DISK=public`

## 6. Mail

- `MAIL_MAILER=smtp`
- `MAIL_HOST=`
- `MAIL_PORT=587`
- `MAIL_USERNAME=`
- `MAIL_PASSWORD=`
- `MAIL_ENCRYPTION=tls`
- `MAIL_FROM_ADDRESS=`
- `MAIL_FROM_NAME=`

## 7. Checklist review

Centang ini sebelum live:

- domain benar
- debug off
- database production bukan database test
- email sender benar
- queue pakai database
- session pakai database
- cache pakai database
- APP_KEY sudah tergenerate
- cron scheduler siap
- cron queue siap
- backup command pernah diuji
- restore drill pernah diuji

## 8. Catatan khusus hosting

Isi kalau ada batasan dari cPanel/hosting:

- path project:
- path php:
- document root:
- larangan shell command:
- ketersediaan composer:
- ketersediaan terminal:
