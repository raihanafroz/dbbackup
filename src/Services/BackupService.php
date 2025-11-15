<?php

namespace RAST\DbBackup\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BackupService {

  /**
   * Run the backup or restore process
   *
   * @param array $options Options:
   *  - no_zip
   *  - no_email
   *  - no_clean
   *  - restore => path to SQL file to restore
   *
   * @return string Path of backup file (or restored file)
   * @throws \Exception
   */
  public function run(array $options = []): string {
    $config = config('dbbackup');
    $db = config('database.connections.mysql');

    if (!empty($options['restore'])) {
      return $this->restoreFromBackup($config, $options['restore']);
    }


    // ---- BACKUP ----
    return $this->backup($config, $db, $options);
  }

  private function backup(array $config, array $db, array $options): string {
    $backupDir = $config['backup_path'];
    if (!File::exists($backupDir)) {
      File::makeDirectory($backupDir, 0755, true);
    }

    $fileName = str_replace(
      ['{db}', '{date}'],
      [$db['database'], date('Y_m_d_His')],
      $config['filename'] ?? 'backup_{db}_{date}.sql'
    );

    $filePath = $backupDir . '/' . $fileName;
    $mysql_dump_path = $config['mysql_path'] . '/mysqldump.exe';
    $cmd = sprintf(
      '"%s" --user=%s --password=%s --host=%s --port=%s %s > "%s"',
      $mysql_dump_path,
      $db['username'],
      $db['password'],
      $db['host'],
      $db['port'] ?? 3306,
      $db['database'],
      $filePath
    );

    exec($cmd);

    if ($config['logging']) {
      Log::info("DB Backup created: " . $filePath);
    }

    // Compression
    if (!$options['no_zip'] && $config['compression']['enabled'] && $config['compression']['type'] === 'zip') {
      $zipPath = $filePath . '.zip';
      $zip = new \ZipArchive;
      if ($zip->open($zipPath, \ZipArchive::CREATE) === TRUE) {
        $zip->addFile($filePath, basename($filePath));
        $zip->close();
        unlink($filePath);
        $filePath = $zipPath;
      }
      if ($config['logging']) {
        Log::info("DB Backup compressed: " . $filePath);
      }
    }

    // Email
    if (!$options['no_email'] && $config['email']['enabled']) {
      Mail::raw($config['email']['message'], function ($msg) use ($filePath, $config) {
        $msg->to($config['email']['to'])
          ->subject($config['email']['subject'])
          ->attach($filePath);
      });
      if ($config['logging']) {
        Log::info("DB Backup emailed to: " . $config['email']['to']);
      }
    }

    // Cleanup old backups
    if (!$options['no_clean'] && $config['cleanup']['enabled'] && $config['cleanup']['keep_last'] > 0) {
      $this->cleanupOldBackups($backupDir, $config['cleanup']['keep_last'], $config['logging']);
    }

    return $filePath;
  }

  /**
   * ---------------------------------------------------------------
   * Delete older backup files and keep only the latest X backups
   * ---------------------------------------------------------------
   *
   * @param string $dir       Directory containing backup files
   * @param int    $keepLast  Number of newest backup files to keep
   * @param bool   $logging   Enable logging for deleted files
   */
  private function cleanupOldBackups(string $dir, int $keepLast, bool $logging): void
  {
    /**
     * ---------------------------------------------------------------
     * 1. Collect all files in the directory and sort by newest first
     * ---------------------------------------------------------------
     */
    $files = collect(File::files($dir))
      ->sortByDesc(fn($file) => $file->getMTime()) // newest → oldest
      ->values();

    /**
     * ---------------------------------------------------------------
     * 2. Slice the list and get only the files that should be deleted
     * ---------------------------------------------------------------
     */
    $filesToDelete = $files->slice($keepLast);

    /**
     * ---------------------------------------------------------------
     * 3. Delete all selected files
     * ---------------------------------------------------------------
     */
    foreach ($filesToDelete as $file) {
      File::delete($file->getRealPath());

      if ($logging) {
        Log::info("DB Backup cleanup: deleted " . $file->getFilename());
      }
    }
  }

  /**
   * Restore a database backup from a given file.
   *
   * @param array|string $config  Backup configuration options
   * @param string       $file    Path to the backup (.sql or .zip)
   *
   * @return string  Status message of the restore process
   */
  private function restoreFromBackup(mixed $config, $file): string {
    try {
      /**
       * ---------------------------------------------------------------
       * 1. Resolve Restore File Path
       * ---------------------------------------------------------------
       */
      $filePath = $config['backup_path'] . '/' . $file;

      if (!File::exists($filePath)) {
        throw new \Exception("Restore file not found: $filePath");
      }

      /**
       * ---------------------------------------------------------------
       * 2. Prepare Temporary Folder (for ZIP extraction)
       * ---------------------------------------------------------------
       */
      $tmpDir = $config['backup_path'] . '/tmp_restore';
      if (!is_dir($tmpDir)) {
        mkdir($tmpDir, 0755, true);
      }

      /**
       * ---------------------------------------------------------------
       * 3. If backup is a ZIP, extract the SQL file
       * ---------------------------------------------------------------
       */
      if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'zip') {

        $zip = new \ZipArchive();

        if ($zip->open($filePath) === true) {
          // Extract contents
          $zip->extractTo($tmpDir);
          $zip->close();

          // Expected extracted SQL file (same filename but .sql)
          $extractedSql = $tmpDir . '/' . basename($filePath, '.zip');

          if (!file_exists($extractedSql)) {
            throw new \Exception("Extracted SQL file not found inside ZIP: $filePath");
          }

          $filePath = $extractedSql;
        } else {
          throw new \Exception("Failed to open ZIP backup: $filePath");
        }
      }

      /**
       * ---------------------------------------------------------------
       * 4. Build MySQL Restore Command
       * ---------------------------------------------------------------
       */

      // Escape the restore file path for safe shell execution
      $filePathEscaped = escapeshellarg(str_replace('\\', '/', $filePath));

      // MySQL binary path
      $mysqlPath = rtrim(env('MYSQL_PATH', 'C:/xampp/mysql/bin'), '/\\') . '/mysql.exe';

      // Database credentials
      $dbHost = env('DB_HOST', '127.0.0.1');
      $dbPort = env('DB_PORT', 3306);
      $dbName = env('DB_DATABASE');
      $dbUser = env('DB_USERNAME');
      $dbPass = env('DB_PASSWORD');

      if ($config['logging']) {
        Log::info("Restoring database from: $filePath");
      }

      // Final shell command for restore
      $cmd = sprintf(
        '"%s" -h %s -P %s -u %s --password=%s %s < %s',
        $mysqlPath,
        $dbHost,
        $dbPort,
        $dbUser,
        escapeshellarg($dbPass),
        $dbName,
        $filePathEscaped
      );

      /**
       * ---------------------------------------------------------------
       * 5. Execute the Restore Command
       * ---------------------------------------------------------------
       */
      exec($cmd, $output, $returnVar);

      if ($returnVar !== 0) {
        throw new \Exception("Database restore failed. Command returned code $returnVar.");
      }

      if ($config['logging']) {
        Log::info("Database successfully restored from: $filePath");
      }

      /**
       * ---------------------------------------------------------------
       * 6. Cleanup Temporary Files (if ZIP extracted)
       * ---------------------------------------------------------------
       */
      if (isset($extractedSql) && file_exists($extractedSql)) {
        unlink($extractedSql);
      }

      if (is_dir($tmpDir)) {
        rmdir($tmpDir);
      }

      return 'Backup restored successfully!';
    } catch (\Exception $e) {
      return 'Restore failed: ' . $e->getMessage();
    }
  }
}
