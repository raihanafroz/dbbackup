<?php

namespace RAST\DbBackup\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BackupService
{

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
  public function run(array $options = []): string
  {
    $config = config('dbbackup');
    $db = config('database.connections.mysql');

    // ---- RESTORE ----
    if (!empty($options['restore'])) {
      $filePath = $options['restore'];

      if (!File::exists($filePath)) {
        throw new \Exception("Restore file not found: $filePath");
      }

      // If zip, extract first
      if (pathinfo($filePath, PATHINFO_EXTENSION) === 'zip') {
        $zip = new \ZipArchive;
        if ($zip->open($filePath) === TRUE) {
          $extractPath = dirname($filePath) . '/restore_' . time() . '.sql';
          $zip->extractTo(dirname($filePath), basename($filePath, '.zip') . '.sql');
          $zip->close();
          $filePath = dirname($filePath) . '/' . basename($filePath, '.zip') . '.sql';
        } else {
          throw new \Exception("Cannot open zip file: $filePath");
        }
      }

      // Build restore command
      $cmd = sprintf(
        '"mysql" --user=%s --password=%s --host=%s --port=%s %s < "%s"',
        $db['username'],
        $db['password'],
        $db['host'],
        $db['port'] ?? 3306,
        $db['database'],
        $filePath
      );

      exec($cmd, $output, $returnVar);

      if ($returnVar !== 0) {
        throw new \Exception("Database restore failed. Command returned code $returnVar.");
      }

      if ($config['logging']) {
        Log::info("DB Restored from: " . $filePath);
      }

      return $filePath;
    }

    // ---- BACKUP ----
    return $this->backup($config, $db, $options);
  }
  private function backup(array $config, array $db, array $options): string
  {
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

    $cmd = sprintf(
      '"%s" --user=%s --password=%s --host=%s --port=%s %s > "%s"',
      $config['mysqldump_path'],
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
      $zip = new ZipArchive;
      if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
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
   * Delete older backup files except latest X
   */
  private function cleanupOldBackups(string $dir, int $keepLast, bool $logging): void
  {
    $files = collect(File::files($dir))
      ->sortByDesc(fn ($file) => $file->getMTime())
      ->values();

    $toDelete = $files->slice($keepLast);

    foreach ($toDelete as $file) {
      File::delete($file->getRealPath());
      if ($logging) {
        Log::info("DB Backup cleanup: deleted " . $file->getFilename());
      }
    }
  }
}
