<?php

declare(strict_types=1);

namespace Andika\Tameng;

use Andika\Tameng\Commands\GeneratePermissionsCommand;
use Andika\Tameng\Commands\InstallCommand;
use Andika\Tameng\Commands\SuperAdminCommand;
use Exception;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class TamengServiceProvider extends PackageServiceProvider
{
    public static string $name = 'tameng';

    public static string $viewNamespace = 'tameng';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasCommands($this->getCommands())
            ->hasConfigFile();

        if (file_exists($package->basePath('/../resources/lang'))) {
            $package->hasTranslations();
        }

        if (file_exists($package->basePath('/../resources/views'))) {
            $package->hasViews(static::$viewNamespace);
        }
    }

    public function packageBooted(): void
    {
        if (app()->runningInConsole()) {
            foreach (app(Filesystem::class)->files(__DIR__ . '/../stubs/') as $file) {
                $this->publishes([
                    $file->getRealPath() => base_path("stubs/tameng/{$file->getFilename()}"),
                ], 'tameng-stubs');
            }
        }

        Gate::before(function ($user, $ability) {
            if ($user === null || ! method_exists($user, 'hasRole') || ! config('tameng.super_admin.enabled', true)) {
                return null;
            }

            if ($user->hasRole($this->superAdminRole())) {
                return true;
            }

            return null;
        });

        if (config('tameng.register_role_policy', true)) {
            $policyClass = (string) config('tameng.policies.namespace', 'App\\Policies') . '\\RolePolicy';

            if (class_exists($policyClass)) {
                Gate::policy(config('permission.models.role'), $policyClass);
            }
        }
    }

    protected function superAdminRole(): string
    {
        try {
            return TamengPlugin::get()->getSuperAdminRole();
        } catch (Exception) {
            return (string) config('tameng.super_admin.name', 'super_admin');
        }
    }

    /** @return array<class-string> */
    protected function getCommands(): array
    {
        return [
            GeneratePermissionsCommand::class,
            InstallCommand::class,
            SuperAdminCommand::class,
        ];
    }
}
