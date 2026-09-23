<?php

declare(strict_types=1);

namespace Andika\Tameng\Commands;

use Andika\Tameng\Filament\Resources\RoleResource;
use Andika\Tameng\Support\ModelHelper;
use Andika\Tameng\Support\PermissionHelper;
use Andika\Tameng\TamengPlugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Str;

class SyncPermissionsCommand extends Command
{
    public $signature = 'tameng:sync {--panel=} {--dry-run} {--force}';

    public $description = 'Sync permissions and policies with current panel entities';

    public function handle(Filesystem $files): int
    {
        $permissionModel = ModelHelper::permissionModelClass();
        $separator = (string) config('tameng.permission.separator', '_');
        $case = (string) config('tameng.permission.case', 'snake');
        $methods = (array) config('tameng.policies.methods', []);
        $subject = (string) config('tameng.resources.subject', 'model');
        $generatePermissions = (bool) config('tameng.permission.generate', true);

        $created = 0;
        $policiesWritten = 0;
        $panels = $this->panels();

        foreach ($panels as $panel) {
            if (! TamengPlugin::forPanel($panel)->shouldDiscoverEntities()) {
                continue;
            }

            $guard = $panel->getAuthGuard();
            $userModel = $this->resolveUserModel($guard);

            $exclude = array_map('strval', (array) config('tameng.resources.exclude', []));

            foreach ($panel->getResources() as $resource) {
                if (in_array($resource, $exclude, true) || $resource === RoleResource::class) {
                    continue;
                }

                $entity = PermissionHelper::entityName($resource, $subject);

                foreach ($methods as $action) {
                    $permission = PermissionHelper::permissionName($entity, $action, $separator, $case);

                    if (! $permissionModel::where('name', $permission)->where('guard_name', $guard)->exists()) {
                        if ($this->option('dry-run')) {
                            $this->info("  Would create permission: {$permission}");
                        } else {
                            $permissionModel::findOrCreate($permission, $guard);
                        }
                        $created++;
                    }
                }
            }

            if ($generatePermissions) {
                foreach ($panel->getResources() as $resource) {
                    if (in_array($resource, $exclude, true) || $resource === RoleResource::class) {
                        continue;
                    }

                    $entity = PermissionHelper::entityName($resource, $subject);
                    $className = Str::studly($entity) . 'Policy';
                    $path = config('tameng.policies.path', app_path('Policies')) . '/' . $className . '.php';

                    if (! $files->exists($path)) {
                        if ($this->option('dry-run')) {
                            $this->info("  Would write policy: {$className}");
                        } else {
                            $this->writePolicy($resource, $separator, $case, $methods, $files, $userModel);
                        }
                        $policiesWritten++;
                    }
                }
            }
        }

        if ($this->option('dry-run')) {
            $this->info("Would create {$created} missing permissions and {$policiesWritten} missing policies.");
            $this->info('Run without --dry-run to create them.');
        } else {
            $this->components->twoColumnDetail('Permissions created', (string) $created);
            $this->components->twoColumnDetail('Policies written', (string) $policiesWritten);
            $this->info('Run "php artisan permission:cache-reset" to refresh the permission cache.');
        }

        return self::SUCCESS;
    }

    /** @return array<Panel> */
    protected function panels(): array
    {
        $panels = Filament::getPanels();

        if ($panelId = $this->option('panel')) {
            $panels = array_values(array_filter(
                $panels,
                fn ($panel): bool => $panel->getId() === $panelId
            ));
        }

        return $panels;
    }

    protected function resolveUserModel(string $guard): ?string
    {
        $provider = config("auth.guards.{$guard}.provider");

        if ($provider === null) {
            return null;
        }

        /** @var class-string<Model>|null $model */
        $model = config("auth.providers.{$provider}.model");

        return ($model !== null && class_exists($model)) ? $model : null;
    }

    protected function writePolicy(string $resource, string $separator, string $case, array $methods, Filesystem $files, ?string $userModel = null): bool
    {
        $entity = PermissionHelper::entityName($resource, (string) config('tameng.resources.subject', 'model'));
        $className = Str::studly($entity) . 'Policy';
        $path = config('tameng.policies.path', app_path('Policies')) . '/' . $className . '.php';

        $namespace = rtrim((string) config('tameng.policies.namespace', 'App\\Policies'), '\\');
        $singleParamMethods = (array) config('tameng.policies.single_parameter_methods', []);
        $resourceModel = PermissionHelper::resolveModelClass($resource);

        $userType = ($userModel !== null && class_exists($userModel)) ? class_basename($userModel) : null;
        $modelType = ($resourceModel !== null && class_exists($resourceModel)) ? class_basename($resourceModel) : null;

        $ownershipEnabled = (bool) config('tameng.policies.ownership.enabled', false);
        $foreignKey = (string) config('tameng.policies.ownership.foreign_key', 'user_id');
        $hasResolver = config('tameng.policies.ownership.resolver') !== null;

        $methodsContent = collect($methods)
            ->map(function (string $action) use ($entity, $separator, $case, $singleParamMethods, $userType, $modelType, $ownershipEnabled, $foreignKey, $hasResolver): string {
                $method = Str::camel($action);
                $permission = addslashes(PermissionHelper::permissionName($entity, $action, $separator, $case));
                $isSingleParam = in_array($action, $singleParamMethods, true);

                $userParam = $userType !== null ? "{$userType} \$user" : '$user';
                $modelParam = $modelType !== null ? "{$modelType} \$model" : '$model';
                $param = $isSingleParam ? $userParam : "{$userParam}, {$modelParam}";

                $body = '';

                if ($ownershipEnabled && ! $isSingleParam) {
                    $body .= "        if (! \$user->can('{$permission}')) {\n";
                    $body .= "            return false;\n";
                    $body .= "        }\n\n";

                    if ($hasResolver) {
                        $body .= "        return call_user_func(config('tameng.policies.ownership.resolver'), \$model, \$user);\n";
                    } else {
                        $body .= "        return \$model->{$foreignKey} === \$user->id;\n";
                    }
                } else {
                    $body .= "        return \$user->can('{$permission}');\n";
                }

                return <<<PHP
    public function {$method}({$param}): bool
    {
{$body}    }
PHP;
            })
            ->implode("\n\n");

        $imports = $this->buildImports($namespace, array_filter([$userModel, $resourceModel]));

        $stub = Str::replace(
            ['{namespace}', '{class}', '{imports}', '{methods}'],
            [$namespace, $className, $imports, $methodsContent],
            $files->get($this->policyStubPath()),
        );

        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, $stub);

        $this->components->twoColumnDetail($className, '<fg=green>written</>');

        return true;
    }

    protected function buildImports(string $policyNamespace, array $modelClasses): string
    {
        if ($modelClasses === []) {
            return '';
        }

        $policyNamespace = rtrim($policyNamespace, '\\');

        $baseUserClass = User::class;

        $imports = collect($modelClasses)
            ->filter(fn (?string $class): bool => $class !== null && class_exists($class))
            ->filter(fn (string $class): bool => (string) Str::beforeLast($class, '\\') !== $policyNamespace)
            ->unique();

        $hasUserImport = $imports->contains(fn (string $class): bool => class_basename($class) === 'User');
        if (! $hasUserImport) {
            $imports->prepend($baseUserClass);
        }

        return $imports->map(fn (string $class): string => "use {$class};")->implode("\n");
    }

    protected function policyStubPath(): string
    {
        $appStub = base_path('stubs/tameng/policy.php.stub');

        if (is_file($appStub)) {
            return $appStub;
        }

        return __DIR__ . '/../../stubs/policy.php.stub';
    }
}
