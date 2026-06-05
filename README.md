# Pulse

Desktop-first school management app built with Laravel and NativePHP.

## Stack

- **Laravel 12** — backend and web UI
- **NativePHP (Electron)** — desktop packaging, zero-install launch
- **MySQL** — local development database
- **SQLite** — embedded database for client/desktop deployment
- **Tailwind CSS v4** — UI styled to match Skolaris Pulse

## Features

- Login authentication
- Role-based access control (Admin, Staff, Viewer)
- User and role management
- Mandatory `sys_logs` audit trail on CRUD operations

## Requirements

- PHP 8.3+
- Composer
- Node.js and npm
- MySQL (development)

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Configure `.env` for MySQL development:

```env
APP_NAME=Pulse
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=iskolaris
DB_USERNAME=root
DB_PASSWORD=
```

Run migrations and seeders:

```bash
php artisan migrate
php artisan db:seed
npm run build
php artisan serve
```

Open http://127.0.0.1:8000

## Default Admin Account

| Field | Value |
|-------|-------|
| Email | `admin@pulso.local` |
| Password | `password` |

## Desktop (NativePHP)

### Dev preview (Electron window)

```bash
php artisan native:serve
```

### Build macOS sample app

```bash
php artisan native:build mac arm64
```

Output:

- `dist/Pulse-0.1.0-arm64.dmg` — installer (double-click to install)
- `dist/mac-arm64/Pulse.app` — runnable app bundle

On first launch, the desktop app creates SQLite at `storage/app/iskolaris.sqlite`, runs migrations, and seeds the default admin account.

**Default login:** `admin@pulso.local` / `password`

> macOS may block unsigned builds. Right-click the app → **Open**, or allow it in **System Settings → Privacy & Security**.

## License

MIT
