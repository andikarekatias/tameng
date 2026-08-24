<?php

declare(strict_types=1);

namespace Andika\Tameng;

use Andika\Tameng\Commands\GeneratePermissionsCommand;
use Andika\Tameng\Commands\InstallCommand;
use Andika\Tameng\Commands\SuperAdminCommand;
use Filament\Support\Assets\Asset;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Exception;

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

    public function packageRegistered(): void {}

    public function packageBooted(): void
    {
        FilamentAsset::register(
            $this->getAssets(),
            $this->getAssetPackageName()
        );

        FilamentAsset::registerScriptData(
            $this->getScriptData(),
            $this->getAssetPackageName()
        );

        FilamentIcon::register($this->getIcons());

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

    protected function getAssetPackageName(): ?string
    {
        return 'andika/tameng';
    }

    /** @return array<Asset> */
    protected function getAssets(): array
    {
        return [];
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

    /** @return array<string> */
    protected function getIcons(): array
    {
        return [];
    }

    /** @return array<string> */
    protected function getRoutes(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    protected function getScriptData(): array
    {
        return [];
    }
}
