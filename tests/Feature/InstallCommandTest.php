<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

it('registers the tameng:install command', function () {
    expect(Artisan::all())->toHaveKey('tameng:install');
});

it('skips migration publish when permissions table exists', function () {
    expect(Schema::hasTable('permissions'))->toBeTrue();

    $this->artisan('tameng:install')
        ->expectsOutputToContain('Table [permissions] already exists. Skipping migration publish.')
        ->expectsConfirmation('Config [tameng.php] already exists. Overwrite?', 'yes')
        ->expectsConfirmation('Would you like to star our repo on GitHub?', 'no')
        ->assertExitCode(0);
});

it('publishes tameng config', function () {
    $configPath = config_path('tameng.php');

    if (file_exists($configPath)) {
        unlink($configPath);
    }

    $this->artisan('tameng:install')
        ->expectsConfirmation('Would you like to star our repo on GitHub?', 'no')
        ->assertExitCode(0);

    expect(file_exists($configPath))->toBeTrue();
});
