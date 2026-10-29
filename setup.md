# AgriTimbang --- Local Development Setup

This guide explains how to run the AgriTimbang Laravel backend on a
local development machine.

## Prerequisites

Install PHP 8.2 or later, Composer, MySQL, Git, and Node.js/npm if the
Laravel/Vite frontend assets are used.

```bash
php -v
composer --version
```

## 1. Clone the Repository

```bash
git clone https://github.com/ShemHooks/agritimbang-lara.git
cd agritimbang
```

## 2. Install Dependencies

```bash
composer install
```

## 3. Create the Environment File

Windows Command Prompt:

```bash
copy .env.example .env
```

PowerShell, macOS, or Linux:

```bash
cp .env.example .env
```

## 4. Generate the Application Key

```bash
php artisan key:generate
```

## 5. Create the MySQL Database

Create an empty database named `agritimbang`.

```sql
CREATE DATABASE agritimbang
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

## 6. Configure `.env`

Configure the database connection for your own local MySQL installation:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=agritimbang
DB_USERNAME=root
DB_PASSWORD=
```

Use the actual port, username, and password configured on your machine.
A custom MySQL installation may use a port other than `3306`.

## 7. Run Migrations and Seed Roles

For a new installation:

```bash
php artisan migrate --seed
```

The current seeder initializes the predefined AgriTimbang roles required
by the application.

During development, if you intentionally want to erase the local
database and rebuild it:

```bash
php artisan migrate:fresh --seed
```

> **Warning:** `migrate:fresh` deletes all existing tables and local
> data.

## 8. Create the Initial System Administrator

AgriTimbang does not expose public Administrator registration. Create
the initial System Administrator from the terminal:

```bash
php artisan agritimbang:create-admin
```

Follow the prompts for the administrator details and password. The
generated account is provisioned as an active, verified Administrator.

## 9. Start Laravel

```bash
php artisan serve
```

Laravel normally starts at:

```text
http://127.0.0.1:8000
```

The local API base is:

```text
http://127.0.0.1:8000/api
```

## 10. Useful Development Commands

```bash
php artisan serve
php artisan route:list --path=api
php artisan migrate
php artisan db:seed
php artisan migrate:fresh --seed
php artisan agritimbang:create-admin
php artisan optimize:clear
```

## Troubleshooting

### Database connection error

Verify `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and
`DB_PASSWORD` in `.env`, then run:

```bash
php artisan optimize:clear
```

### `Unauthenticated`

Make sure the request contains:

```text
Authorization: Bearer <token>
```

### `403` / Not Authorized

The user is authenticated but does not have the role required by the
route. Administrator endpoints require the `admin` role.

### Roles are missing

```bash
php artisan db:seed
```

For a disposable development database:

```bash
php artisan migrate:fresh --seed
```

### Application key is missing

```bash
php artisan key:generate
```

### Configuration changes are not taking effect

```bash
php artisan optimize:clear
```

## Fresh Setup --- Quick Version

```bash
git clone https://github.com/ShemHooks/agritimbang-lara.git
cd agritimbang
composer install
cp .env.example .env
php artisan key:generate
# Configure MySQL credentials in .env
php artisan migrate --seed
php artisan agritimbang:create-admin
php artisan serve
```

If frontend dependencies are required:

```bash
npm install
npm run dev
```

## AgriTimbang Account Provisioning

```text
System Administrator
    |
    +-- creates Municipality
    |
    +-- creates LGU Authority for a Municipality
            |
            +-- creates LGU Encoder accounts for that Municipality

Farmer
    |
    +-- self-registers
    +-- completes verification
```

The initial System Administrator is created only through the server-side
Artisan command. Public registration is reserved for Farmer accounts.

Thank YOU GUYS
