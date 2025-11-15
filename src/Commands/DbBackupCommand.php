<?php

namespace RAST\DbBackup\Commands;

use Illuminate\Console\Command;
use RAST\DbBackup\Services\BackupService;
use Exception;

class DbBackupCommand extends Command
{
  /**
   * Artisan command signature.
   *
   * Options:
   *  --no-zip       Run backup without compression
   *  --no-email     Skip sending email
   *  --no-clean     Skip deleting old backups
   */
  protected $signature = 'rast:db-backup
                            {--no-zip : Disable compression for this backup}
                            {--no-email : Do not send backup email}
                            {--no-clean : Skip cleaning old backups}';

  /**
   * Command description.
   */
  protected $description = 'Backup MySQL database into the storage folder with optional compression, cleanup, and email.';

  /**
   * Execute the command.
   */
  public function handle(): void
  {
    $this->info("Starting database backup...");
    $this->line("");

    try {
      $service = new BackupService();

      // Run backup with temporary options
      $path = $service->run([
        'no_zip'   => $this->option('no-zip'),
        'no_email' => $this->option('no-email'),
        'no_clean' => $this->option('no-clean')
      ]);

      $this->newLine();
      $this->info("✔ Backup completed successfully!");
      $this->line("File saved at: ");
      $this->comment($path);

    } catch (Exception $e) {

      $this->newLine();
      $this->error("✘ Backup failed!");
      $this->line($e->getMessage());
    }
  }
}
