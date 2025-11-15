# 📦 RAST DB Backup

[![Issues](https://img.shields.io/github/issues/raihanafroz/dbbackup?style=flat-square)](https://github.com/raihanafroz/dbbackup/issues)
[![Forks](https://img.shields.io/github/forks/raihanafroz/dbbackup?style=flat-square)](https://github.com/raihanafroz/dbbackup/network/members)
[![Stars](https://img.shields.io/github/stars/raihanafroz/dbbackup?style=flat-square)](https://github.com/raihanafroz/dbbackup/stargazers)
[![Total Downloads](https://img.shields.io/packagist/dt/rast/dbbackup?style=flat-square)](https://packagist.org/packages/rast/dbbackup)
[![License](https://poser.pugx.org/rast/dbbackup/license.svg)](https://packagist.org/packages/rast/dbbackup)

A lightweight Laravel package for creating MySQL database backups via CLI or scheduler.

**Features:**

* Custom backup directories
* Configurable `mysqldump` path
* Optional ZIP compression
* Optional email notification with backup attachment
* Restore backups from `.sql` or `.zip` files

---

## 🔧 Requirements

* PHP 8.2+
* Laravel 12.x
* MySQL database
* `mysqldump` installed and executable on the server

---

## 🚀 Installation

### 1. Add the package to your project




```bash
composer require rast/dbbackup
```

### 2. Register the Service Provider

If your package is not auto-discovered, add it in `config/app.php`:

```php
'providers' => [
    // Other providers...
    RAST\DbBackup\DbBackupServiceProvider::class,
],
```

---

## ⚙️ Configuration

### 1. Publish the config

```bash
php artisan vendor:publish --provider="RAST\DbBackup\DbBackupServiceProvider" --tag=config
```

This creates: `config/dbbackup.php`

### 2. Config Options

```php
return [

    'backup_path' => storage_path('app/db_backups'),

    'mysqldump_path' => env('MYSQL_DUMP_PATH', 'mysqldump'),

    'filename' => 'backup_{db}_{date}.sql',

    'compression' => [
        'enabled' => true,
        'type' => 'zip',
    ],

    'cleanup' => [
        'enabled' => true,
        'keep_last' => 10,
    ],

    'email' => [
        'enabled' => false,
        'to' => 'admin@example.com',
        'subject' => 'Database Backup',
        'message' => 'Your scheduled database backup is attached.',
    ],

    'logging' => true,
];
```

**Key Options:**

| Option         | Description                                                |
| -------------- | ---------------------------------------------------------- |
| backup_path    | Directory to store backups                                 |
| mysqldump_path | Path to `mysqldump` (full path recommended)                |
| filename       | Backup file naming pattern (`{db}` and `{date}` supported) |
| compression    | Enable ZIP compression                                     |
| cleanup        | Auto-delete old backups, keeps latest `n` files            |
| email          | Send backup via email                                      |
| logging        | Log backup actions to `laravel.log`                        |

### 3. Environment Variables

Add to `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_user
DB_PASSWORD=your_password

MYSQL_DUMP_PATH=/usr/bin/mysqldump
```

> On cPanel/shared hosting: usually `/usr/bin/mysqldump`
> On Windows/XAMPP: `C:/xampp/mysql/bin`

---

## ⚡ Usage

### 1. Manual Backup

```bash
php artisan rast:db-backup
```

Example output:

```
Backup complete: storage/app/db_backups/backup_mydb_2025_11_15_173027.sql.zip
```

### 2. Optional Command Flags

| Flag        | Description               |
|-------------|---------------------------|
| --no-zip    | Skip compression          |
| --no-email  | Skip email                |
| --no-clean  | Skip deleting old backups |
| --restore   | Restore the old backups   |

Backup Example:

```bash
php artisan rast:db-backup --no-zip --no-email
```

Restore Example: 
```bash
php artisan rast:db-backup --restore=backup.sql
```
 * Supports .sql and .zip backups
 * Automatically extracts .zip before restoring

```bash
php artisan rast:db-backup --restore=backup_mydb_2025_11_15_173027.sql.zip
```

### 3. Scheduling Backups

In `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('rast:db-backup')->dailyAt('03:00');
}
```

Add cron entry:

```cron
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```


## 🔐 Security Recommendations

* Limit `backup_path` permissions: `chmod 770 storage/app/db_backups`
* Avoid storing backups in version control
* Optionally encrypt backup files (ZIP with password or GPG)
* For production, consider offloading backups to S3, FTP, or cloud storage

---

## 🛠 Troubleshooting

| Issue                          | Solution                                                 |
| ------------------------------ | -------------------------------------------------------- |
| `mysqldump: command not found` | Set `MYSQL_DUMP_PATH` to full path: `/usr/bin/mysqldump` |
| Permission denied writing file | Ensure `backup_path` exists and is writable              |
| Backup file empty              | Check DB credentials and privileges                      |
| Email not sent                 | Verify mail configuration in `.env`                      |

---

## 🔄 Extending the Package

* **Multiple databases**: Loop through multiple connections in `config/database.php`
* **Custom file names**: Include environment, app name, or custom prefix
* **Cloud storage**: Upload backups to S3, FTP, or remote servers
* **Additional compression formats**: `.gz`, `.tar.gz` using PHP functions or CLI tools

---

Made with ❤️ by **RAST**
