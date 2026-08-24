<?php

declare(strict_types=1);

namespace Andika\Tameng\Commands;

use Andika\Tameng\TamengPlugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Contracts\Role;
use Spatie\Permission\PermissionRegistrar;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class SuperAdminCommand extends Command
{
    public $signature = 'tameng:super-admin
        {--user= : ID of user to be made super admin.}
        {--panel= : Panel ID to get the configuration from.}
        {--tenant= : Team/Tenant ID to assign role to user.}
    ';

    public $description = 'Assign the super admin role to a user';

    public function handle(): int
    {
        $panel = $this->panel();

        if ($panel === null) {
            return self::FAILURE;
        }

        $tenancyEnabled = config('tameng.tenant_model') !== null || config('permission.teams');

        if ($tenancyEnabled && $this->option('tenant') === null) {
            $this->components->error('Tenancy is configured. Please provide the team/tenant id via the --tenant option.');

            return self::FAILURE;
        }

        if ($this->option('tenant') !== null && ! config('permission.teams')) {
            $this->components->error('Spatie teams are not enabled. Enable the "teams" feature of spatie/laravel-permission first.');

            return self::FAILURE;
        }

        if ($tenantId = $this->option('tenant')) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        }

        $user = $this->resolveUser($panel);

        if ($user === null) {
            return self::FAILURE;
        }

        $role = $this->role($panel);

        if (! method_exists($user, 'assignRole')) {
            $this->components->error('The panel user model must use the Spatie "HasRoles" trait.');

            return self::FAILURE;
        }

        $user
            ->unsetRelation('roles')
            ->unsetRelation('permissions')
            ->assignRole($role);

        $loginUrl = $panel->getLoginUrl();

        $this->components->info("Success! {$user->getAttribute('email')} may now log in at {$loginUrl}.");

        Log::info("Super admin role [{$role->name}] assigned to user [{$user->getKey()}] via artisan command.");

        if (! config('tameng.super_admin.enabled', true)) {
            $this->components->warn('The super admin bypass is disabled (tameng.super_admin.enabled). The role was assigned but will not bypass authorization checks.');
        }

        return self::SUCCESS;
    }

    protected function panel(): ?Panel
    {
        if ($panelId = $this->option('panel')) {
            $panel = Filament::getPanels()[$panelId] ?? null;

            if ($panel === null) {
                $this->components->error("Panel [{$panelId}] was not found.");

                return null;
            }

            return $panel;
        }

        if (($current = Filament::getCurrentPanel()) !== null) {
            return $current;
        }

        $panels = array_values(Filament::getPanels());

        if (count($panels) === 1) {
            return $panels[0];
        }

        $this->components->error('Could not determine the Filament panel to use. Pass one with the --panel option.');

        return null;
    }

    protected function resolveUser(Panel $panel): ?Model
    {
        $guardName = $panel->getAuthGuard();

        $providerName = config("auth.guards.{$guardName}.provider");

        if (! is_string($providerName)) {
            $this->components->error("No auth provider is configured for guard [{$guardName}].");

            return null;
        }

        $model = config("auth.providers.{$providerName}.model");

        if (! is_string($model)) {
            $this->components->error("No user model is configured for provider [{$providerName}].");

            return null;
        }

        /** @var class-string<Model> $model */
        $model = $model;

        if ($userId = $this->option('user')) {
            if (! is_numeric($userId)) {
                $this->components->error('User ID must be a numeric value.');

                return null;
            }

            try {
                return $model::query()->findOrFail($userId);
            } catch (ModelNotFoundException) {
                $this->components->error("User with ID [{$userId}] was not found.");

                return null;
            }
        }

        $count = $model::query()->count();

        if ($count === 1) {
            return $model::query()->first();
        }

        if ($count > 1) {
            return $this->selectUser($model);
        }

        return $this->createUser($model);
    }

    /** @param  class-string<Model>  $model */
    protected function selectUser(string $model): Model
    {
        $query = $model::query();

        if (method_exists($model, 'roles')) {
            $query->with('roles');
        }

        $this->table(
            ['ID', 'Name', 'Email', 'Roles'],
            $query->get()->map(function (Model $user): array {
                return [
                    'id' => $user->getKey(),
                    'name' => $user->getAttribute('name'),
                    'email' => $user->getAttribute('email'),
                    'roles' => $user->relationLoaded('roles')
                        ? $user->getRelation('roles')->pluck('name')->implode(', ')
                        : '',
                ];
            })->all(),
        );

        return $model::query()->findOrFail(text(
            label: 'Please provide the user ID to be set as super admin',
            required: true,
        ));
    }

    /** @param  class-string<Model>  $model */
    protected function createUser(string $model): Model
    {
        return $model::query()->create([
            'name' => text(label: 'Name', required: true),
            'email' => text(
                label: 'Email address',
                required: true,
                validate: fn (string $email): ?string => match (true) {
                    ! filter_var($email, FILTER_VALIDATE_EMAIL) => 'The email address must be valid.',
                    $model::query()->where('email', $email)->exists() => 'A user with this email address already exists.',
                    default => null,
                },
            ),
            'password' => Hash::make(password(
                label: 'Password',
                required: true,
                validate: fn (string $value): ?string => strlen($value) < 12
                    ? 'The password must be at least 12 characters.'
                    : null,
            )),
        ]);
    }

    protected function role(Panel $panel): Role
    {
        /** @var class-string<Role> $roleClass */
        $roleClass = app(PermissionRegistrar::class)->getRoleClass();

        return $roleClass::findOrCreate(
            TamengPlugin::forPanel($panel)->getSuperAdminRole(),
            $panel->getAuthGuard(),
        );
    }
}
