<?php

namespace RAST\DbBackup\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BackupService
{
  /**
   * Run the database backup.
   *
   * @param array $options = [
   *     'no_zip'   => false,
   *     'no_email' => false,
   *     'no_clean' => false
   * ]
   *
   * @return string
   */
  public function run(array $options = []): string {
    $config = config('dbbackup');
    $db = config('database.connections.mysql');

    // ---- Prepare folder ----
    $backupDir = $config['backup_path'];

    if (!File::exists($backupDir)) {
      File::makeDirectory($backupDir, 0755, true);
    }

    // ---- Filename pattern ----
    $fileName = str_replace(
      ['{db}', '{date}'],
      [$db['database'], date('Y_m_d_H_i_s')],
      $config['filename'] ?? 'backup_{db}_{date}.sql'
    );

    $filePath = $backupDir . '/' . $fileName;

    // ---- Build mysqldump command ----
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

    // ---- Logging ----
    if ($config['logging']) {
      Log::info("DB Backup created: " . $filePath);
    }

    /**
     * ============================================================
     *           COMPRESSION (skipped if --no-zip)
     * ============================================================
     */
    if (!$options['no_zip'] &&
      $config['compression']['enabled'] &&
      $config['compression']['type'] === 'zip') {

      $zipPath = $filePath . '.zip';
      $zip = new \ZipArchive;

      if ($zip->open($zipPath, \ZipArchive::CREATE) === TRUE) {
        $zip->addFile($filePath, basename($filePath));
        $zip->close();
        unlink($filePath); // remove original SQL
      }

      $filePath = $zipPath;

      if ($config['logging']) {
        Log::info("DB Backup compressed: " . $filePath);
      }
    }

    /**
     * ============================================================
     *                     EMAIL (skipped if --no-email)
     * ============================================================
     */
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

    /**
     * ============================================================
     *           CLEANUP OLD BACKUPS (skipped if --no-clean)
     * ============================================================
     */
    if (
      !$options['no_clean'] &&
      $config['cleanup']['enabled'] &&
      $config['cleanup']['keep_last'] > 0
    ) {
      $this->cleanupOldBackups(
        $backupDir,
        $config['cleanup']['keep_last'],
        $config['logging']
      );
    }

    return $filePath;
  }

  /**
   * Delete older backup files except latest X files.
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
