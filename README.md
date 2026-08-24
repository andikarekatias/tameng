# Tameng — Custom Filament authorization plugin

[![Latest Version on Packagist](https://img.shields.io/packagist/v/andika/tameng.svg?style=flat-square)](https://packagist.org/packages/andika/tameng)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/andikarekatias/tameng/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/andikarekatias/tameng/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/andikarekatias/tameng/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/andikarekatias/tameng/actions?query=workflow%3A"Fix+PHP+code+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/andika/tameng.svg?style=flat-square)](https://packagist.org/packages/andika/tameng)

Tameng adds role & permission management to your Filament panels on top of [spatie/laravel-permission](https://github.com/spatie/laravel-permission).

### Features

- 🛡️ **Role Management** — manage roles and permissions from a single page with embedded permission cards
    - 📦 Resource Permissions
    - 📄 Page Permissions
    - 🧩 Widget Permissions
    - ✨ Custom Permissions
- 🤖 **Automatic Permission Generation** — scans panels and creates permissions by convention
    - 📜 Policy generation from publishable stubs
    - 🏷️ Configurable case formatting (snake, kebab, pascal, camel, upper_snake)
    - 🔗 Entity discovery across all panels
- 👑 **Super Admin Bypass** — users with the configured role skip all authorization checks
- 🔄 **Multi-tenancy Support** — automatic tenant scoping with spatie teams + Filament panel tenancy
- 🎨 **Intuitive UI** — Shield-inspired card grid layout with icons per entity type
- ⚡ **Fine-grained CLI Tooling** — install, generate, and super-admin commands

## Requirements

- PHP ^8.2
- Laravel 11+
- Filament 5.x (panels)
- spatie/laravel-permission ^8.0

## Installation

Install the package and run the installer:

```bash
composer require andika/tameng
php artisan tameng:install
```

The installer publishes Spatie's `config/permission.php` and the `create_permission_tables` migration, publishes our `config/tameng.php`, and asks whether you want to run `php artisan migrate`. After installation, it shows next steps:

```
tameng has been installed!

Next Steps:
  1. php artisan tameng:generate
     Generate permissions for your Filament panels
  2. php artisan tameng:super-admin user@email.com
     Assign super admin to a user
  3. php artisan permission:cache-reset
     Clear permission cache
```

Add the `HasRoles` trait to your `User` model:

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

Register the plugin on your panel provider:

```php
use Andika\Tameng\TamengPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(TamengPlugin::make());
}
```

## Usage

### Quickstart

```bash
php artisan tameng:super-admin     # create your first admin (interactive)
php artisan tameng:generate        # permissions + policies for panel entities
php artisan permission:cache-reset # refresh the spatie cache
```

Log in at your panel's URL (e.g. `/dashboard`) — the **Tameng** navigation item appears in the sidebar. Open it to manage roles and their permissions via embedded permission cards.

### Super admin role

Users with the role configured in `config/tameng.php` bypass every authorization check:

```php
'super_admin' => [
    'enabled' => false,  // set to true to enable the bypass
    'name' => 'super_admin',
],
```

Set `enabled` to `true` to activate the bypass — any user with this role skips all authorization checks. The role then behaves like any other when disabled.

Assign the role from the command line:

```bash
php artisan tameng:super-admin
```

The command picks a panel from `--panel` (falling back to the current or the only registered panel), then:

- `--user=` — the ID of the user to promote (no prompt)
- **no users** — asks for a name, email and password and creates the user before assigning the role
- **one user** — promotes them automatically
- **multiple users** — lists them and asks for the ID to promote

```bash
php artisan tameng:super-admin --panel=admin --user=1
```

With spatie teams enabled, pass the team/tenant the role should be scoped to:

```bash
php artisan tameng:super-admin --tenant=1
```

Or do it in code:

```php
Role::findOrCreate('super_admin');
$user->assignRole('super_admin');
```

### Permissions & policies

Generate permissions and policies for every entity registered in your panels:

```bash
php artisan tameng:generate
```

This creates permissions such as `user_view_any`, `user_view`, `user_create`, `user_update`, `user_delete`, `user_delete_any` and writes `app/Policies/UserPolicy.php`:

```php
namespace App\Policies;

class UserPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('user_view_any');
    }

    public function view($user, $model): bool
    {
        return $user->can('user_view');
    }

    // ...
}
```

Existing policies are never overwritten unless you pass `--force`:

```bash
php artisan tameng:generate --force
```

Generate permissions for a single panel only:

```bash
php artisan tameng:generate --panel=admin
```

Policies are rendered from a stub. To customize the generated policy shape, publish the stub and edit it (placeholders: `{namespace}`, `{class}`, `{methods}`):

```bash
php artisan vendor:publish --tag="tameng-stubs"
```

After changing permissions, refresh the permission cache:

```bash
php artisan permission:cache-reset
```

### Configuration

All options live in `config/tameng.php`. Publish it with:

```bash
php artisan vendor:publish --tag="tameng-config"
```

#### Permission builder

```php
'permission' => [
    'separator' => '_',
    'case' => 'snake',       // snake|kebab|pascal|camel|upper_snake|lower_snake
    'generate' => true,      // false skips permission creation (policies still written)
    'name_max_length' => 255,
],
```

> **Separator + case constraint:** `_` cannot pair with snake/upper_snake, `-` cannot pair with kebab. Examples: pascal + `:` → `User:ViewAny`; kebab + `-` → `user-view-any`.

#### Slug

The URL path for the role management page. Defaults to `tameng` (e.g. `/dashboard/tameng`):

```php
'slug' => 'tameng',   // → /dashboard/tameng
```

#### Excludes

Skip entities from permission generation:

```php
'resources' => [
    'subject' => 'model',    // 'model' uses the resource's model class name
    'exclude' => [
        // \App\Filament\Resources\FooResource::class,
    ],
],

'pages' => [
    'exclude' => [
        \Filament\Pages\Dashboard::class,
    ],
],

'widgets' => [
    'exclude' => [
        \Filament\Widgets\AccountWidget::class,
        \Filament\Widgets\FilamentInfoWidget::class,
    ],
],
```

#### Custom permissions

Extra permission keys created by `tameng:generate` alongside entity-derived permissions:

```php
'custom_permissions' => [
    'system_backup',
    'audit_log',
],
```

#### Policies

```php
'policies' => [
    'path' => app_path('Policies'),
    'namespace' => 'App\\Policies',
    'methods' => [
        'view_any', 'view', 'create', 'update', 'delete',
        'delete_any', 'restore', 'restore_any',
        'force_delete', 'force_delete_any',
    ],
    'single_parameter_methods' => [
        'view_any', 'create', 'delete_any',
        'restore_any', 'force_delete_any',
    ],
],
```

Methods in `single_parameter_methods` generate `($user)` signatures; others generate `($user, $model)`.

Set `register_role_policy` to `true` to generate a `RolePolicy` for the spatie Role model and register it with `Gate::policy`:

```php
'register_role_policy' => true,
```

#### Tenant model

Tameng integrates with Filament's multi-tenancy system and spatie teams. When both are enabled:

1. A `SyncTenant` middleware automatically syncs the spatie team ID from the current Filament tenant
2. The role list is scoped to the current tenant
3. New roles are automatically associated with the current tenant
4. Permission cache is only invalidated when the tenant changes

**Setup:**

1. Enable spatie teams in `config/permission.php`:

```php
'teams' => true,
```

2. Set your tenant model in `config/tameng.php`:

```php
'tenant_model' => App\Models\Team::class,
```

3. Register the tenant on your Filament panel:

```php
use App\Models\Team;

return $panel
    ->tenant(Team::class)
    ->plugin(TamengPlugin::make());
```

4. Make your User model implement `Filament\Models\Contracts\HasTenants`:

```php
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;

class User extends Authenticatable implements HasTenants
{
    use HasRoles;

    public function tenants(Panel $panel): Collection
    {
        return $this->teams;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return true;
    }
}
```

5. Assign roles scoped to a tenant via CLI:

```bash
php artisan tameng:super-admin --user=1 --tenant=1
```

#### Entity discovery

When enabled (default), `tameng:generate` scans every registered panel and generates permissions and policies for all of its resources, pages and widgets. Disable globally via config or per-panel via the plugin API:

```php
'entity_discovery' => true,
```

```php
TamengPlugin::make()
    ->entityDiscovery(false);   // disable for this panel only
```

#### Localization

Translated permission labels in the role editor:

```php
'localization' => [
    'enabled' => false,
    'key' => 'tameng::tameng.permissions',
],
```

Publish translations:

```bash
php artisan vendor:publish --tag="tameng-translations"
```

### Plugin configuration

Per-panel overrides via the plugin API:

```php
TamengPlugin::make()
    ->superAdminRole('super_admin')   // per-panel role name override
    ->entityDiscovery(false);         // disable entity scanning in tameng:generate
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [andikarekatias](https://github.com/andikarekatias)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.