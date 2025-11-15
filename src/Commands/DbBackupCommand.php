<?php

namespace RAST\DbBackup\Commands;

use Illuminate\Console\Command;
use RAST\DbBackup\Services\BackupService;
use Exception;

class DbBackupCommand extends Command
{
  protected $signature = 'rast:db-backup
                            {--restore= : Path to .sql or .zip file to restore}
                            {--no-zip : Disable compression for backup}
                            {--no-email : Do not send backup email}
                            {--no-clean : Skip cleaning old backups}';

  protected $description = 'Backup or restore MySQL database with optional compression, cleanup, and email.';

  public function handle(): void
  {
    $service = new BackupService();

    try {

      // ---- RESTORE MODE ----
      if ($this->option('restore')) {
        $path = $this->option('restore');

        $this->info("Starting database restore...");
        $this->line("");

        $restoredFile = $service->run([
          'restore' => $path
        ]);

        $this->newLine();
        $this->info("✔ Restore completed successfully!");
        $this->comment("Restored from: $restoredFile");
        return;
      }

      // ---- BACKUP MODE ----
      $this->info("Starting database backup...");
      $this->line("");

      $backupPath = $service->run([
        'no_zip'   => $this->option('no-zip'),
        'no_email' => $this->option('no-email'),
        'no_clean' => $this->option('no-clean'),
      ]);

      $this->newLine();
      $this->info("✔ Backup completed successfully!");
      $this->comment("File saved at: $backupPath");

    } catch (Exception $e) {

      $this->newLine();
      $this->error("✘ Operation failed!");
      $this->line($e->getMessage());
    }
  }
}
