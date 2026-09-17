# Laravel 12 + Skote + Blade + DataTables Starter

Starter project Laravel 12 dengan UI Skote, Blade, authentication, user/role management, dan server-side DataTables.

## Project

Fondasi aplikasi admin yang reusable untuk project berikutnya. Stack:

- Laravel 12
- PHP 8.3+
- Blade + Bootstrap 5
- Skote Admin Template
- Vite
- MySQL
- Spatie Laravel Permission
- jQuery DataTables + Bootstrap 5
- Yajra Laravel DataTables

## Requirement

- PHP 8.3+
- Composer
- Node.js 18+ dan npm
- MySQL 8+

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Buat database MySQL `starter`, lalu:

```bash
php artisan migrate --seed
npm install
npm run dev
php artisan serve
```

## Environment

Variabel penting di `.env`:

```env
APP_NAME=Starter
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=starter
DB_USERNAME=root
DB_PASSWORD=

ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=password
```

## Database

Gunakan MySQL. Tabel dasar:

- `users`
- `password_reset_tokens`
- `sessions`
- `cache`
- `jobs`
- `roles`
- `permissions`
- `model_has_roles`
- `model_has_permissions`
- `role_has_permissions`
- `settings`

## Migration

```bash
php artisan migrate
```

Kolom `users.status` ditambahkan untuk activate/deactivate.

## Seeder

```bash
php artisan db:seed
```

Seeder:

- `RolePermissionSeeder`
- `UserSeeder`
- `MenuSeeder`
- `DatabaseSeeder`

Roles:

- Super Admin
- Admin
- User

Permissions:

- `dashboard.view`
- `users.view`
- `users.create`
- `users.edit`
- `users.delete`
- `users.impersonate`
- `roles.view`
- `roles.create`
- `roles.edit`
- `roles.delete`
- `menus.view`
- `menus.create`
- `menus.edit`
- `menus.delete`
- `settings.update`

Credential Super Admin diambil dari `ADMIN_EMAIL` dan `ADMIN_PASSWORD`.

Akun demo tambahan:

- `staff@example.com` / `password` (Admin)
- `user@example.com` / `password` (User)

## NPM

```bash
npm install
npm run dev
npm run build
```

Vite memuat asset custom aplikasi, termasuk DataTables Bootstrap 5.

## DataTables

Semua halaman listing memakai DataTables server-side.

Pola:

```text
Blade → DataTables AJAX → Laravel Route → Controller → Eloquent → JSON
```

Komponen reusable:

```blade
<x-data-table
    id="users-table"
    :ajax="route('users.data')"
    :columns="$columns"
/>
```

Endpoint JSON:

- `/users/data`
- `/roles/data`
- `/menus/data`

Fitur: search, sorting, pagination, page length, responsive, empty state, processing indicator, dan action column.

## Authentication

- `/login`
- `/logout`

Halaman login memakai layout Skote. Semua halaman aplikasi dilindungi middleware `auth`.

## Role & Permission

Authorization memakai Spatie Laravel Permission.

Super Admin melewati seluruh permission check. Sidebar dibangun dari modul **Menus** dan item-nya mengikuti permission Spatie.

## Project Structure

```text
app/
├── Enums/
├── Helpers/
├── Http/Controllers/
├── Http/Requests/
├── Models/
└── Policies/

resources/
├── css/app.css
├── js/app.js
└── views/
    ├── layouts/
    ├── components/
    ├── auth/
    ├── dashboard/
    ├── users/
    ├── roles/
    ├── menus/
    └── settings/
```

## Skote Structure

Asset Skote terpisah dari asset custom Vite:

```text
public/themes/skote/
├── css/
├── js/
├── libs/
├── images/
└── fonts/
```

## Development

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run dev
php artisan serve
```

Buka `http://127.0.0.1:8000` lalu login dengan `ADMIN_EMAIL` / `ADMIN_PASSWORD`.

Untuk modul listing baru, salin pola Users: Form Request, Policy, DataTables endpoint `/data`, dan komponen `<x-data-table />`.
