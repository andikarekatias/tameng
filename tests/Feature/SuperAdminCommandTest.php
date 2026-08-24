<?php

use Andika\Tameng\TamengPlugin;
use Andika\Tameng\Tests\Fixtures\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

it('assigns the super admin role to the only user automatically', function () {
    $user = User::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'secret',
    ]);

    $this->artisan('tameng:super-admin')
        ->expectsOutputToContain('Success! admin@example.com')
        ->assertExitCode(0);

    config(['tameng.super_admin.enabled' => true]);
    expect($user->fresh()->hasRole(config('tameng.super_admin.name')))->toBeTrue()
        ->and(Role::where('name', config('tameng.super_admin.name'))->where('guard_name', 'web')->exists())->toBeTrue()
        ->and(Gate::forUser($user->fresh())->check('some_random_ability'))->toBeTrue();
});

it('assigns the super admin role to the user given via the user option', function () {
    User::create(['name' => 'First', 'email' => 'first@example.com', 'password' => 'secret']);
    $target = User::create(['name' => 'Second', 'email' => 'second@example.com', 'password' => 'secret']);

    $this->artisan('tameng:super-admin', ['--user' => $target->getKey()])
        ->expectsOutputToContain('Success! second@example.com')
        ->assertExitCode(0);

    expect($target->fresh()->hasRole(config('tameng.super_admin.name')))->toBeTrue()
        ->and(User::first()->hasRole(config('tameng.super_admin.name')))->toBeFalse();
});

it('fails when the requested user does not exist', function () {
    $this->artisan('tameng:super-admin', ['--user' => 999])
        ->expectsOutputToContain('User with ID [999] was not found.')
        ->assertExitCode(1);
});

it('fails when the requested panel does not exist', function () {
    $this->artisan('tameng:super-admin', ['--panel' => 'missing'])
        ->expectsOutputToContain('Panel [missing] was not found.')
        ->assertExitCode(1);
});

it('fails when a tenant is given but spatie teams are disabled', function () {
    $this->artisan('tameng:super-admin', ['--tenant' => 1])
        ->expectsOutputToContain('Spatie teams are not enabled.')
        ->assertExitCode(1);
});

it('uses the panel plugin super admin role override', function () {
    TamengPlugin::get()->superAdminRole('root');

    $user = User::create([
        'name' => 'Root',
        'email' => 'root@example.com',
        'password' => 'secret',
    ]);

    $this->artisan('tameng:super-admin')->assertExitCode(0);

    expect($user->fresh()->hasRole('root'))->toBeTrue()
        ->and(Role::where('name', 'root')->exists())->toBeTrue();
});

it('fails when a tenant model is configured but no tenant is given', function () {
    config(['tameng.tenant_model' => 'App\\Models\\Team']);

    $this->artisan('tameng:super-admin')
        ->expectsOutputToContain('Tenancy is configured')
        ->assertExitCode(1);
});
