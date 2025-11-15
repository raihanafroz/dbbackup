<?php

namespace RAST\DbBackup;

use Illuminate\Support\ServiceProvider;

class DbBackupServiceProvider extends ServiceProvider {
  /**
   *  Register any application services.
   *
   * @return void
   */
  public function register(): void {
    $this->mergeConfigFrom(__DIR__ . '/../config/dbbackup.php', 'dbbackup');

    if ($this->app->runningInConsole()) {
      $this->commands([
        \RAST\DbBackup\Commands\DbBackupCommand::class,
      ]);
    }
  }

  /**
   *  Bootstrap any application services.
   *
   * @return void
   */
  public function boot(): void {
    // Publish config
    $this->publishes([
      __DIR__ . '/../config/dbbackup.php' => config_path('dbbackup.php')
    ], 'config');

    // Register console command
//    if ($this->app->runningInConsole()) {
    $this->commands([
      \RAST\DbBackup\Commands\DbBackupCommand::class,
    ]);
//    }
  }
}
