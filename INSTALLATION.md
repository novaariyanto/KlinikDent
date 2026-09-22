# Instalasi KlinikDent

Panduan deploy production ke Linux VPS. Installer membaca kebutuhan aktual project (Laravel 12, PHP 8.3, MySQL, Vite, queue database, scheduler) — bukan checklist Laravel generik.

## 1. System requirements

Wajib:

- PHP 8.3+
- Composer 2
- Node.js 18+ dan npm (Vite 6)
- MySQL 8+ (database sudah dibuat, atau installer dapat membuatnya jika user MySQL punya hak `CREATE`)
- Ekstensi PHP: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `gd`, `hash`, `json`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `session`, `tokenizer`, `xml`, `zip`

Disarankan:

- `pcntl` dan `posix` untuk queue worker
- Supervisor (worker antrian)
- cron (Laravel scheduler)
- `mysqldump` (backup via `php artisan app:backup`)
- Apache atau Nginx, document root = `public/`

Tidak wajib untuk instalasi ini:

- Redis (cache, queue, dan session default memakai database)
- Reverb / Soketi / Pusher (broadcasting tidak dipakai)
- Horizon, Passport, S3, Firebase

## 2. Upload source code

```bash
cd /var/www
git clone <repo> klinikdent
cd klinikdent
```

Atau unggah arsip source, lalu pastikan owner folder sesuai user deploy.

## 3. Configure `.env`

```bash
cp .env.example .env
nano .env
```

Minimal yang harus diisi sebelum production:

```env
APP_NAME=KlinikDent
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda
APP_FORCE_HTTPS=true

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=klinikdent
DB_USERNAME=klinikdent
DB_PASSWORD=********

QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_ENCRYPT=true
CACHE_STORE=database
FILESYSTEM_DISK=local

ADMIN_EMAIL=anda@domain
ADMIN_PASSWORD=ganti-segera
```

`APP_KEY` boleh kosong — installer akan meng-generate jika belum ada.

Variable opsional (bukan blocker installer):

| Grup | Keterangan |
| --- | --- |
| `MAIL_*` | Default `log`. SMTP juga bisa diisi dari Settings aplikasi. |
| `MIDTRANS_*` | Cadangan jika Payment Gateway di aplikasi belum diisi. Wajib hanya jika `MIDTRANS_ENABLED=true`. |
| `WHATSAPP_API_BASE_URL` | Default `https://whatscrm.id`. API key diisi per klinik. |
| `SATUSEHAT_*` / `BPJS_*` | Kredensial per klinik. `.env` hanya URL & mode fake/sandbox. |
| `AWS_*` | Hanya jika `FILESYSTEM_DISK=s3`. |
| `REDIS_*` | Hanya jika queue/cache/session diubah ke redis. |
| `SENTRY_LARAVEL_DSN` | Tercantum di `.env.example`, paket Sentry **tidak** ada di `composer.json`. |

Jangan set `APP_DEBUG=true` di production. Installer akan menolak itu.

## 4. Run installer

```bash
chmod +x install.sh installer/install.sh
./installer/install.sh
```

Setara: `./install.sh`.

Flags:

```bash
./installer/install.sh --dry-run
./installer/install.sh --skip-seed
./installer/install.sh --skip-build
./installer/install.sh --skip-services
./installer/install.sh --force
```

| Flag | Fungsi |
| --- | --- |
| `--dry-run` | Cek requirement, env, dan koneksi DB. Tidak migrate/seed/build/overwrite. |
| `--skip-seed` | Lewati seeder. |
| `--skip-build` | Lewati `npm ci` dan `npm run build`. |
| `--skip-services` | Lewati Supervisor & cron. |
| `--force` | Non-interaktif. Memakai `.env` yang ada. Seeder dilewati jika database sudah berisi user. |

Jika `.env` sudah ada, installer **tidak** menimpa tanpa konfirmasi.

Log: `storage/logs/installer.log` (password / `APP_KEY` / secret di-mask).

## 5. Database setup

1. Koneksi dicek via PDO (`pdo_mysql`).
2. Jika schema belum ada, installer menawarkan `CREATE DATABASE`.
3. Production: opsi backup (`php artisan app:backup`) sebelum migrate jika tabel `migrations` sudah ada.
4. Migration: `php artisan migrate --force` — **tidak** memakai `migrate:fresh` / `db:wipe`.

Seeder:

- **Production (database kosong):** seeder inti — roles/permissions, menu, template WhatsApp global, paket SaaS, dan Super Admin dari `ADMIN_EMAIL` / `ADMIN_PASSWORD`.
- **Development:** default `DatabaseSeeder` (tenant demo, semua role, data operasional).
- **Database sudah berisi user:** default skip. `UserSeeder` dapat mereset password demo; `RegistrationSeeder` dapat menambah pasien baru.

Setelah login pertama, ganti password Super Admin.

## 6. Queue setup

Project memakai `QUEUE_CONNECTION=database` dan tiga job antrian:

- `SendWhatsAppTemplateJob`
- `SyncSatuSehatEncounter`
- `CheckBpjsMembership`

Tanpa worker, WhatsApp, SATUSEHAT, dan cek BPJS tidak terkirim.

Installer menulis template Supervisor ke `storage/app/supervisor-klinikdent-worker.conf` dan, jika berjalan sebagai root di Linux, memasangnya ke `/etc/supervisor/conf.d/`.

Manual:

```bash
sudo cp storage/app/supervisor-klinikdent-worker.conf /etc/supervisor/conf.d/klinikdent-worker.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

Jangan gunakan `queue:listen` di production.

## 7. Scheduler setup

Dijadwalkan di `routes/console.php`:

| Command | Jadwal |
| --- | --- |
| `saas:expire-subscriptions` | setiap hari 01:15 |
| `app:backup` | setiap hari 02:30 |
| `whatsapp:dispatch` | setiap menit |

Cron yang dipasang installer (tanpa duplikat):

```cron
* * * * * cd /var/www/klinikdent && php artisan schedule:run >> /dev/null 2>&1
```

## 8. Realtime

Tidak ada. `BROADCAST_CONNECTION=log`. Jangan memasang Reverb/Soketi untuk project ini.

## 9. Web server

Document root **harus** `public/`. Ada `public/.htaccess` untuk Apache.

Contoh Apache:

```apache
<VirtualHost *:80>
    ServerName domain-anda
    DocumentRoot /var/www/klinikdent/public

    <Directory /var/www/klinikdent/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Contoh Nginx:

```nginx
server {
    listen 80;
    server_name domain-anda;
    root /var/www/klinikdent/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
}
```

Webhook Midtrans: `POST /midtrans/notification` (CSRF dikecualikan di `bootstrap/app.php`). Health check Laravel: `GET /up`.

## 10. SSL

Pasang sertifikat (Let's Encrypt / panel hosting), lalu:

```env
APP_URL=https://domain-anda
APP_FORCE_HTTPS=true
SESSION_SECURE_COOKIE=true
```

`ForceHttps` hanya aktif jika `APP_ENV=production` dan `APP_FORCE_HTTPS=true`.

Cek:

```bash
php artisan app:production-check
```

## 11. Troubleshooting

| Gejala | Periksa |
| --- | --- |
| Installer berhenti di PHP | Versi < 8.3 atau ekstensi kurang (`pdo_mysql`, `gd`, `zip`, …) |
| Database connection failed | `DB_*`, database exists, user MySQL, bind-address |
| Vite / CSS admin rusak | `public/build/manifest.json` — jalankan ulang tanpa `--skip-build` |
| Logo klinik 404 | `php artisan storage:link` dan permission `storage/app/public` |
| Job tidak jalan | Supervisor worker + `QUEUE_CONNECTION` bukan `sync` |
| Langganan tidak kedaluwarsa / backup tidak ada | cron `schedule:run` |
| Permission denied log/upload | `chown -R www-data:www-data storage bootstrap/cache` (sesuaikan user web) |
| `APP_DEBUG` ditolak | Production wajib `APP_DEBUG=false` |

Jangan `chmod -R 777`.

## 12. Re-running installer

Aman dijalankan berulang:

- `composer install` / `npm ci` / `npm run build` idempotent
- `migrate --force` hanya menerapkan migration baru
- `storage:link` aman jika tautan sudah ada
- cron & Supervisor tidak diduplikasi
- seeder default **dilewati** jika tabel `users` sudah berisi data (`--force` juga skip)

Yang tidak otomatis diulang tanpa konfirmasi:

- overwrite `.env`
- `DatabaseSeeder` penuh
- `migrate:fresh`

## 13. Backup

```bash
php artisan app:backup
```

File: `storage/app/backups/`. Retensi: `BACKUP_KEEP_DAYS` (default 14). Restore:

```bash
php artisan app:restore {file} --force
```

Restore menimpa database aktif — konfirmasi wajib kecuali `--force`.

## 14. Update deployment

```bash
cd /var/www/klinikdent
php artisan down
git pull
./installer/install.sh --skip-seed --skip-services
php artisan up
```

Atau manual:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci
npm run build
php artisan optimize
```

Restart worker setelah update kode job:

```bash
sudo supervisorctl restart klinikdent-worker:*
```

## Quick start

```bash
cp .env.example .env
nano .env
./installer/install.sh
```

Development lokal (tanpa Supervisor/cron):

```bash
./installer/install.sh --skip-services
php artisan serve
```
