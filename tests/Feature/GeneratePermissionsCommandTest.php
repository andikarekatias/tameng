<?php

use Andika\Tameng\TamengPlugin;
use Andika\Tameng\Tests\Fixtures\User;
use Andika\Tameng\Tests\Fixtures\UserResource;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app('files')->delete([
        app_path('Policies/UserPolicy.php'),
        app_path('Policies/RolePolicy.php'),
    ]);
});

it('generates permissions and policies for panel resources', function () {
    $this->artisan('tameng:generate')
        ->expectsOutputToContain('Permissions created')
        ->assertExitCode(0);

    expect(config('permission.models.permission')::where('name', 'user_view_any')->exists())->toBeTrue()
        ->and(config('permission.models.permission')::where('name', 'user_create')->exists())->toBeTrue()
        ->and(config('permission.models.permission')::where('name', 'user_delete_any')->exists())->toBeTrue();

    $policy = app_path('Policies/UserPolicy.php');

    expect(file_exists($policy))->toBeTrue()
        ->and(file_get_contents($policy))
        ->toContain('class UserPolicy')
        ->toContain("return \$user->can('user_view_any');")
        ->toContain("return \$user->can('user_force_delete_any');");
});

it('does not overwrite existing policies without force', function () {
    app('files')->ensureDirectoryExists(app_path('Policies'));
    app('files')->put(app_path('Policies/UserPolicy.php'), '<?php // customized');

    $this->artisan('tameng:generate')->assertExitCode(0);

    expect(app('files')->get(app_path('Policies/UserPolicy.php')))->toBe('<?php // customized');
});

it('bypasses authorization checks for the super admin role', function () {
    config(['tameng.super_admin.enabled' => true]);
    config(['tameng.super_admin.enabled' => true]);
    Role::findOrCreate(config('tameng.super_admin.name'));

    $user = User::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'secret',
    ])->assignRole(config('tameng.super_admin.name'));

    expect(Gate::forUser($user)->check('some_random_ability'))->toBeTrue();
});

it('does not bypass authorization checks when the super admin is disabled', function () {
    config(['tameng.super_admin.enabled' => false]);

    Role::findOrCreate(config('tameng.super_admin.name'));

    $user = User::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'secret',
    ])->assignRole(config('tameng.super_admin.name'));

    expect(Gate::forUser($user)->check('some_random_ability'))->toBeFalse();
});

it('does not crash when authorization is checked for a guest', function () {
    expect(Gate::forUser(null)->check('some_random_ability'))->toBeFalse();
});

it('uses the panel plugin super admin role override', function () {
    config(['tameng.super_admin.enabled' => true]);
    config(['tameng.super_admin.enabled' => true]);
    TamengPlugin::get()->superAdminRole('root');

    Role::findOrCreate('root');

    $user = User::create([
        'name' => 'Root',
        'email' => 'root@example.com',
        'password' => 'secret',
    ])->assignRole('root');

    expect(Gate::forUser($user)->check('some_random_ability'))->toBeTrue();
});

it('fails when the requested panel does not exist', function () {
    $this->artisan('tameng:generate', ['--panel' => 'missing'])
        ->expectsOutputToContain('Panel [missing] was not found.')
        ->assertExitCode(1);
});

it('overwrites existing policies with force', function () {
    app('files')->ensureDirectoryExists(app_path('Policies'));
    app('files')->put(app_path('Policies/UserPolicy.php'), '<?php // customized');

    $this->artisan('tameng:generate', ['--force' => true])->assertExitCode(0);

    expect(app('files')->get(app_path('Policies/UserPolicy.php')))->toContain('class UserPolicy');
});

// --- New tests: shield-config alignment ---

it('excludes Dashboard permission by default', function () {
    $this->artisan('tameng:generate')->assertExitCode(0);

    expect(config('permission.models.permission')::where('name', 'dashboard_view')->exists())->toBeFalse();
});

it('skips excluded resources', function () {
    config(['tameng.resources.exclude' => [UserResource::class]]);

    $this->artisan('tameng:generate')->assertExitCode(0);

    expect(config('permission.models.permission')::where('name', 'user_view_any')->exists())->toBeFalse();
});

it('uses resource model name when subject is model', function () {
    config(['tameng.resources.subject' => 'model']);

    $this->artisan('tameng:generate')->assertExitCode(0);

    expect(config('permission.models.permission')::where('name', 'user_view_any')->exists())->toBeTrue();
});

it('formats permissions with pascal case and colon separator', function () {
    config([
        'tameng.permission.case' => 'pascal',
        'tameng.permission.separator' => ':',
    ]);

    $this->artisan('tameng:generate')->assertExitCode(0);

    expect(config('permission.models.permission')::where('name', 'User:ViewAny')->exists())->toBeTrue()
        ->and(config('permission.models.permission')::where('name', 'user_view_any')->exists())->toBeFalse();

    $policy = app_path('Policies/UserPolicy.php');

    expect(file_exists($policy))->toBeTrue()
        ->and(file_get_contents($policy))
        ->toContain("return \$user->can('User:ViewAny');");
});

it('formats permissions with kebab case', function () {
    config([
        'tameng.permission.case' => 'kebab',
        'tameng.permission.separator' => '-',
    ]);

    $this->artisan('tameng:generate')->assertExitCode(0);

    expect(config('permission.models.permission')::where('name', 'user-view-any')->exists())->toBeTrue()
        ->and(config('permission.models.permission')::where('name', 'user_view_any')->exists())->toBeFalse();
});

it('formats permissions with upper snake case', function () {
    config(['tameng.permission.case' => 'upper_snake']);

    $this->artisan('tameng:generate')->assertExitCode(0);

    expect(config('permission.models.permission')::where('name', 'USER_VIEW_ANY')->exists())->toBeTrue()
        ->and(config('permission.models.permission')::where('name', 'user_view_any')->exists())->toBeFalse();
});

it('skips permission creation when generate is false', function () {
    config(['tameng.permission.generate' => false]);

    $this->artisan('tameng:generate')->assertExitCode(0);

    expect(config('permission.models.permission')::where('name', 'user_view_any')->exists())->toBeFalse();

    $policy = app_path('Policies/UserPolicy.php');

    expect(file_exists($policy))->toBeTrue()
        ->and(file_get_contents($policy))
        ->toContain('class UserPolicy');
});

it('creates custom permissions', function () {
    config(['tameng.custom_permissions' => ['system_backup', 'audit_log']]);

    $this->artisan('tameng:generate')->assertExitCode(0);

    expect(config('permission.models.permission')::where('name', 'system_backup')->exists())->toBeTrue()
        ->and(config('permission.models.permission')::where('name', 'audit_log')->exists())->toBeTrue();
});

it('respects custom policy methods with replicate', function () {
    config([
        'tameng.policies.methods' => [
            'view_any', 'view', 'create', 'update', 'delete',
            'delete_any', 'restore', 'restore_any',
            'force_delete', 'force_delete_any', 'replicate',
        ],
    ]);

    $this->artisan('tameng:generate', ['--force' => true])->assertExitCode(0);

    expect(config('permission.models.permission')::where('name', 'user_replicate')->exists())->toBeTrue();

    $policy = app_path('Policies/UserPolicy.php');

    expect(file_get_contents($policy))
        ->toContain('public function replicate($user, $model): bool');
});

it('uses single parameter methods for policy signatures', function () {
    $this->artisan('tameng:generate', ['--force' => true])->assertExitCode(0);

    $policy = file_get_contents(app_path('Policies/UserPolicy.php'));

    expect($policy)
        ->toContain('public function viewAny($user): bool')
        ->toContain('public function view($user, $model): bool')
        ->toContain('public function create($user): bool')
        ->toContain('public function update($user, $model): bool');
});

it('writes and registers the role policy', function () {
    $this->artisan('tameng:generate', ['--force' => true])->assertExitCode(0);

    $rolePolicy = app_path('Policies/RolePolicy.php');

    expect(file_exists($rolePolicy))->toBeTrue()
        ->and(file_get_contents($rolePolicy))
        ->toContain('class RolePolicy')
        ->toContain("return \$user->can('role_view_any');");

    Role::findOrCreate(config('tameng.super_admin.name'));

    $role = Role::findOrCreate('test_role');
    $user = User::create(['name' => 'Tester', 'email' => 'tester@example.com', 'password' => 'secret']);

    expect(Gate::forUser($user)->check('viewAny', $role))->toBeFalse();

    $user->assignRole(config('tameng.super_admin.name'));

    config(['tameng.super_admin.enabled' => true]);
    expect(Gate::forUser($user->fresh())->check('viewAny', $role))->toBeTrue();
});
