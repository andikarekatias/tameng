<?php

beforeEach(function () {
    app('files')->delete([
        app_path('Policies/UserPolicy.php'),
        app_path('Policies/RolePolicy.php'),
    ]);
});

it('checks permissions and reports success when consistent', function () {
    $this->artisan('tameng:generate')->assertExitCode(0);

    $this->artisan('tameng:check')
        ->expectsOutputToContain('All permissions are consistent')
        ->assertExitCode(0);
});

it('checks permissions and reports missing permissions', function () {
    // Delete a permission that should exist after tameng:generate
    config('permission.models.permission')::where('name', 'user_view_any')->delete();

    $this->artisan('tameng:check')
        ->expectsOutputToContain('not defined')
        ->assertExitCode(1);
});

it('syncs missing permissions in dry-run mode', function () {
    $this->artisan('tameng:generate')->assertExitCode(0);

    config('permission.models.permission')::where('name', 'user_view_any')->delete();

    $this->artisan('tameng:sync', ['--dry-run' => true])
        ->expectsOutputToContain('Would create permission: user_view_any')
        ->assertExitCode(0);
});

it('syncs missing permissions and creates them', function () {
    $this->artisan('tameng:generate')->assertExitCode(0);

    config('permission.models.permission')::where('name', 'user_view_any')->delete();

    $this->artisan('tameng:sync')
        ->expectsOutputToContain('Permissions created')
        ->assertExitCode(0);

    expect(config('permission.models.permission')::where('name', 'user_view_any')->exists())->toBeTrue();
});

it('syncs with panel option', function () {
    $this->artisan('tameng:generate')->assertExitCode(0);

    $this->artisan('tameng:sync', ['--panel' => 'admin'])
        ->expectsOutputToContain('Permissions created')
        ->assertExitCode(0);
});
