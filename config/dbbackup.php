<?php

return [

  /*
  |--------------------------------------------------------------------------
  | Backup Storage Path
  |--------------------------------------------------------------------------
  |
  | All generated backup files will be stored here.
  | Example: storage/app/db_backups
  |
  */

  'backup_path' => storage_path('app/db_backups'),


  /*
  |--------------------------------------------------------------------------
  | Mysqldump Binary Path
  |--------------------------------------------------------------------------
  |
  | Full path to mysqldump. On Windows, set the EXE path.
  | Example for XAMPP:
  |  On Windows
  |   MYSQL_DUMP_PATH="C:/xampp/mysql/bin"
  |
  |  On cPanel/shared hosting: usually
  |   MYSQL_DUMP_PATH="/usr/bin/mysqldump"
  */

  'mysql_path' => env('MYSQL_DUMP_PATH', 'C:/xampp/mysql/bin'),


  /*
  |--------------------------------------------------------------------------
  | Filename Format
  |--------------------------------------------------------------------------
  |
  | You can customize the backup filename.
  | Available tokens:
  | {date}   - Y_m_d_H_i_s format
  | {db}     - database name
  |
  */

  'filename' => 'backup_{db}_{date}.sql',


  /*
  |--------------------------------------------------------------------------
  | Compression Settings
  |--------------------------------------------------------------------------
  |
  | If zip is enabled, the SQL file will be compressed.
  | Supported: zip or none
  |
  */

  'compression' => [
    'enabled' => true,
    'type' => 'zip', // options: zip, none
  ],


  /*
  |--------------------------------------------------------------------------
  | Auto Delete Old Backups
  |--------------------------------------------------------------------------
  |
  | Automatically remove old backups.
  |
  | keep_last: number of backup files to keep (newest)
  |            0 = disable auto-delete
  |
  */

  'cleanup' => [
    'enabled' => true,
    'keep_last' => 10,
  ],


  /*
  |--------------------------------------------------------------------------
  | Email Backup
  |--------------------------------------------------------------------------
  |
  | Send the generated backup via email as an attachment.
  |
  */

  'email' => [
    'enabled' => false,
    'to' => 'admin@example.com',
    'subject' => 'Database Backup',
    'message' => 'Your scheduled database backup is attached.',
  ],


  /*
  |--------------------------------------------------------------------------
  | Logging
  |--------------------------------------------------------------------------
  |
  | When enabled, logs backup activities using laravel.log
  |
  */

  'logging' => true,

];
