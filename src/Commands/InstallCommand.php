<?php

declare(strict_types=1);

namespace Andika\Tameng\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class InstallCommand extends Command
{
    public $signature = 'tameng:install';

    public $description = 'Install and configure tameng';

    public function handle(): int
    {
        $this->publishPermissionConfig();
        $this->publishPermissionMigration();
        $this->publishTamengConfig();
        $this->askToRunMigrations();
        $this->askToStarRepo();

        $this->newLine();
        $this->info('tameng has been installed!');
        $this->showNextSteps();

        return self::SUCCESS;
    }

    protected function publishPermissionConfig(): void
    {
        if (file_exists(config_path('permission.php'))) {
            $this->components->info('Config [permission.php] already exists. Skipping.');

            return;
        }

        $this->call('vendor:publish', ['--tag' => 'permission-config']);
    }

    protected function publishPermissionMigration(): void
    {
        if (Schema::hasTable('permissions')) {
            $this->components->warn('Table [permissions] already exists. Skipping migration publish.');

            return;
        }

        if (! empty(glob(database_path('migrations/*_create_permission_tables.php')))) {
            $this->components->info('Permission migration already published. Skipping.');

            return;
        }

        $this->call('vendor:publish', ['--tag' => 'permission-migrations']);
    }

    protected function publishTamengConfig(): void
    {
        if (file_exists(config_path('tameng.php')) && ! $this->confirm('Config [tameng.php] already exists. Overwrite?', false)) {
            return;
        }

        $this->call('vendor:publish', ['--tag' => 'tameng-config', '--force' => true]);
    }

    protected function askToRunMigrations(): void
    {
        if (Schema::hasTable('permissions')) {
            return;
        }

        if ($this->confirm('Would you like to run the migrations now?', false)) {
            $this->call('migrate');
        }
    }

    protected function askToStarRepo(): void
    {
        if ($this->confirm('Would you like to star our repo on GitHub?', true)) {
            $this->components->info('Star us on GitHub: https://github.com/andikarekatias/tameng');
        }
    }

    protected function showNextSteps(): void
    {
        $this->newLine();
        $this->info('Next Steps:');
        $this->comment('  1. php artisan tameng:generate');
        $this->comment('     Generate permissions for your Filament panels');
        $this->comment('  2. php artisan tameng:super-admin user@email.com');
        $this->comment('     Assign super admin to a user');
        $this->comment('  3. php artisan permission:cache-reset');
        $this->comment('     Clear permission cache');
        $this->newLine();
    }
}
