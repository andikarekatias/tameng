<?php

declare(strict_types=1);

namespace Andika\Tameng\Commands;

use Andika\Tameng\Filament\Resources\RoleResource;
use Andika\Tameng\Support\ModelHelper;
use Andika\Tameng\Support\PermissionHelper;
use Andika\Tameng\TamengPlugin;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\Resources\Pages\Page as ResourcePage;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Str;

class GeneratePermissionsCommand extends Command
{
    public $signature = 'tameng:generate {--panel= : Generate permissions only for the given panel id} {--force : Overwrite existing policy files} {--with-ownership : Enable ownership enforcement in generated policies}';

    public $description = 'Generate permissions and policies for the entities registered in your Filament panels';

    public function handle(Filesystem $files): int
    {
        $permissionModel = ModelHelper::permissionModelClass();
        $separator = (string) config('tameng.permission.separator', '_');
        $case = (string) config('tameng.permission.case', 'snake');
        $methods = (array) config('tameng.policies.methods', []);
        $generatePermissions = (bool) config('tameng.permission.generate', true);
        $customPermissions = (array) config('tameng.custom_permissions', []);

        if ($this->option('with-ownership')) {
            config(['tameng.policies.ownership.enabled' => true]);
        }

        if ($generatePermissions) {
            $this->validateSeparatorCase($separator, $case);
        }

        $permissionsCreated = 0;
        $policiesWritten = 0;

        $panels = $this->panels();

        if ($panelId = $this->option('panel')) {
            if ($panels === []) {
                $this->error("Panel [{$panelId}] was not found.");

                return self::FAILURE;
            }
        }

        foreach ($panels as $panel) {
            if (! TamengPlugin::forPanel($panel)->shouldDiscoverEntities()) {
                $this->components->twoColumnDetail($panel->getId(), '<fg=yellow>entity discovery disabled</>');

                continue;
            }

            $guard = $panel->getAuthGuard();
            $userModel = $this->resolveUserModel($guard);

            if ($generatePermissions) {
                $this->generateResourcePermissions($panel, $permissionModel, $separator, $case, $methods, $guard);
                $this->generatePagePermissions($panel, $permissionModel, $separator, $case, $guard);
                $this->generateWidgetPermissions($panel, $permissionModel, $separator, $case, $guard);
            }

            foreach ($customPermissions as $permission) {
                $permissionModel::findOrCreate($permission, $guard);
                $permissionsCreated++;
            }

            $skipRolePolicy = config('tameng.register_role_policy', true);

            foreach ($panel->getResources() as $resource) {
                if ($skipRolePolicy && $resource === RoleResource::class) {
                    continue;
                }

                if ($this->writePolicy($resource, $separator, $case, $methods, $files, $userModel, $panel->getId())) {
                    $policiesWritten++;
                }
            }

            if (config('tameng.register_role_policy', true)) {
                $this->writeRolePolicy($separator, $case, $methods, $files, $userModel, $panel->getId());
            }
        }

        $this->components->twoColumnDetail('Permissions created', (string) $permissionsCreated);
        $this->components->twoColumnDetail('Policies written', (string) $policiesWritten);

        $this->info('Run "php artisan permission:cache-reset" to refresh the permission cache.');

        return self::SUCCESS;
    }

    protected function validateSeparatorCase(string $separator, string $case): void
    {
        $conflicts = [
            '_' => ['upper_snake'],
            '-' => ['kebab'],
        ];

        if (isset($conflicts[$separator]) && in_array($case, $conflicts[$separator], true)) {
            $this->components->warn("Separator [{$separator}] conflicts with case [{$case}]. Generated permission names may be malformed.");
        }
    }

    /** @return array<Panel> */
    protected function panels(): array
    {
        $panels = Filament::getPanels();

        if ($panelId = $this->option('panel')) {
            $panels = array_values(array_filter(
                $panels,
                fn (Panel $panel): bool => $panel->getId() === $panelId
            ));
        }

        return $panels;
    }

    protected function generateResourcePermissions(Panel $panel, string $permissionModel, string $separator, string $case, array $methods, string $guard): int
    {
        $subject = (string) config('tameng.resources.subject', 'model');
        $exclude = array_map('strval', (array) config('tameng.resources.exclude', []));
        $scopedToPanel = (bool) config('tameng.permission.scoped_to_panel', false);
        $panelId = $panel->getId();

        $created = 0;

        foreach ($panel->getResources() as $resource) {
            if (in_array($resource, $exclude, true)) {
                continue;
            }

            $entity = PermissionHelper::entityName($resource, $subject);

            foreach ($methods as $action) {
                $permission = PermissionHelper::permissionName($entity, $action, $separator, $case);
                $permission = $scopedToPanel ? "{$panelId}_{$permission}" : $permission;
                $permissionModel::findOrCreate($permission, $guard);
                $created++;
            }
        }

        return $created;
    }

    protected function generatePagePermissions(Panel $panel, string $permissionModel, string $separator, string $case, string $guard): int
    {
        $subject = (string) config('tameng.pages.subject', 'class');
        $exclude = array_map('strval', (array) config('tameng.pages.exclude', []));
        $scopedToPanel = (bool) config('tameng.permission.scoped_to_panel', false);
        $panelId = $panel->getId();

        $created = 0;

        foreach ($panel->getPages() as $page) {
            if (is_a($page, ResourcePage::class, true) || is_a($page, Dashboard::class, true)) {
                continue;
            }

            if (in_array($page, $exclude, true)) {
                continue;
            }

            $permission = PermissionHelper::permissionName(PermissionHelper::entityName($page, $subject), 'view', $separator, $case);
            $permission = $scopedToPanel ? "{$panelId}_{$permission}" : $permission;
            $permissionModel::findOrCreate($permission, $guard);
            $created++;
        }

        return $created;
    }

    protected function generateWidgetPermissions(Panel $panel, string $permissionModel, string $separator, string $case, string $guard): int
    {
        $subject = (string) config('tameng.widgets.subject', 'class');
        $exclude = array_map('strval', (array) config('tameng.widgets.exclude', []));
        $scopedToPanel = (bool) config('tameng.permission.scoped_to_panel', false);
        $panelId = $panel->getId();

        $created = 0;

        foreach ($panel->getWidgets() as $widget) {
            $class = is_object($widget) ? $widget::class : $widget;

            if (in_array($class, $exclude, true)) {
                continue;
            }

            $permission = PermissionHelper::permissionName(PermissionHelper::entityName($class, $subject), 'view', $separator, $case);
            $permission = $scopedToPanel ? "{$panelId}_{$permission}" : $permission;
            $permissionModel::findOrCreate($permission, $guard);
            $created++;
        }

        return $created;
    }

    protected function writePolicy(string $resource, string $separator, string $case, array $methods, Filesystem $files, ?string $userModel = null, ?string $panelId = null): bool
    {
        $entity = PermissionHelper::entityName($resource, (string) config('tameng.resources.subject', 'model'));
        $className = Str::studly($entity) . 'Policy';
        $path = config('tameng.policies.path', app_path('Policies')) . '/' . $className . '.php';

        if ($files->exists($path) && ! $this->option('force')) {
            $this->components->twoColumnDetail($className, '<fg=yellow>skipped, already exists</>');

            return false;
        }

        $namespace = rtrim((string) config('tameng.policies.namespace', 'App\\Policies'), '\\');
        $singleParamMethods = (array) config('tameng.policies.single_parameter_methods', []);
        $resourceModel = PermissionHelper::resolveModelClass($resource);
        $scopedToPanel = (bool) config('tameng.permission.scoped_to_panel', false);

        $userType = ($userModel !== null && class_exists($userModel)) ? class_basename($userModel) : null;
        $modelType = ($resourceModel !== null && class_exists($resourceModel)) ? class_basename($resourceModel) : null;

        $ownershipEnabled = (bool) config('tameng.policies.ownership.enabled', false);
        $foreignKey = (string) config('tameng.policies.ownership.foreign_key', 'user_id');
        $hasResolver = config('tameng.policies.ownership.resolver') !== null;
        $hasBefore = config('tameng.policies.before') !== null;
        $hasAfter = config('tameng.policies.after') !== null;

        $methodsContent = collect($methods)
            ->map(function (string $action) use ($entity, $separator, $case, $singleParamMethods, $userType, $modelType, $ownershipEnabled, $foreignKey, $hasResolver, $scopedToPanel, $panelId, $hasBefore, $hasAfter): string {
                $method = Str::camel($action);
                $permission = PermissionHelper::permissionName($entity, $action, $separator, $case);
                $permission = $scopedToPanel && $panelId !== null ? "{$panelId}_{$permission}" : $permission;
                $permission = addslashes($permission);
                $isSingleParam = in_array($action, $singleParamMethods, true);

                $userParam = $userType !== null ? "{$userType} \$user" : '$user';
                $modelParam = $modelType !== null ? "{$modelType} \$model" : '$model';
                $param = $isSingleParam ? $userParam : "{$userParam}, {$modelParam}";

                $body = '';

                if ($hasBefore) {
                    $modelArg = $isSingleParam ? 'null' : '$model';
                    $body .= "        \$before = call_user_func(config('tameng.policies.before'), \$user, '{$action}', {$modelArg});\n";
                    $body .= "        if (\$before !== null) {\n";
                    $body .= "            return \$before;\n";
                    $body .= "        }\n\n";
                }

                if ($ownershipEnabled && ! $isSingleParam) {
                    $body .= "        if (! \$user->can('{$permission}')) {\n";
                    $body .= "            return false;\n";
                    $body .= "        }\n\n";

                    if ($hasResolver) {
                        $body .= $hasAfter
                            ? "        \$result = call_user_func(config('tameng.policies.ownership.resolver'), \$model, \$user);\n"
                            : "        return call_user_func(config('tameng.policies.ownership.resolver'), \$model, \$user);\n";
                    } else {
                        $body .= $hasAfter
                            ? "        \$result = \$model->{$foreignKey} === \$user->id;\n"
                            : "        return \$model->{$foreignKey} === \$user->id;\n";
                    }
                } else {
                    $body .= $hasAfter
                        ? "        \$result = \$user->can('{$permission}');\n"
                        : "        return \$user->can('{$permission}');\n";
                }

                if ($hasAfter) {
                    $modelArg = $isSingleParam ? 'null' : '$model';
                    $body .= "\n        return call_user_func(config('tameng.policies.after'), \$user, '{$action}', {$modelArg}, \$result);\n";
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

    protected function writeRolePolicy(string $separator, string $case, array $methods, Filesystem $files, ?string $userModel = null, ?string $panelId = null): void
    {
        $className = 'RolePolicy';
        $path = config('tameng.policies.path', app_path('Policies')) . '/' . $className . '.php';

        if ($files->exists($path) && ! $this->option('force')) {
            $this->components->twoColumnDetail($className, '<fg=yellow>skipped, already exists</>');

            return;
        }

        $namespace = rtrim((string) config('tameng.policies.namespace', 'App\\Policies'), '\\');
        $singleParamMethods = (array) config('tameng.policies.single_parameter_methods', []);
        $entity = 'role';
        $roleModel = ModelHelper::roleModelClass();
        $scopedToPanel = (bool) config('tameng.permission.scoped_to_panel', false);

        $userType = ($userModel !== null && class_exists($userModel)) ? class_basename($userModel) : null;
        $modelType = class_exists($roleModel) ? class_basename($roleModel) : null;

        $ownershipEnabled = (bool) config('tameng.policies.ownership.enabled', false);
        $foreignKey = (string) config('tameng.policies.ownership.foreign_key', 'user_id');
        $hasResolver = config('tameng.policies.ownership.resolver') !== null;
        $hasBefore = config('tameng.policies.before') !== null;
        $hasAfter = config('tameng.policies.after') !== null;

        $methodsContent = collect($methods)
            ->map(function (string $action) use ($entity, $separator, $case, $singleParamMethods, $userType, $modelType, $ownershipEnabled, $foreignKey, $hasResolver, $scopedToPanel, $panelId, $hasBefore, $hasAfter): string {
                $method = Str::camel($action);
                $permission = PermissionHelper::permissionName($entity, $action, $separator, $case);
                $permission = $scopedToPanel && $panelId !== null ? "{$panelId}_{$permission}" : $permission;
                $permission = addslashes($permission);
                $isSingleParam = in_array($action, $singleParamMethods, true);

                $userParam = $userType !== null ? "{$userType} \$user" : '$user';
                $modelParam = $modelType !== null ? "{$modelType} \$model" : '$model';
                $param = $isSingleParam ? $userParam : "{$userParam}, {$modelParam}";

                $body = '';

                if ($hasBefore) {
                    $modelArg = $isSingleParam ? 'null' : '$model';
                    $body .= "        \$before = call_user_func(config('tameng.policies.before'), \$user, '{$action}', {$modelArg});\n";
                    $body .= "        if (\$before !== null) {\n";
                    $body .= "            return \$before;\n";
                    $body .= "        }\n\n";
                }

                if ($ownershipEnabled && ! $isSingleParam) {
                    $body .= "        if (! \$user->can('{$permission}')) {\n";
                    $body .= "            return false;\n";
                    $body .= "        }\n\n";

                    if ($hasResolver) {
                        $body .= $hasAfter
                            ? "        \$result = call_user_func(config('tameng.policies.ownership.resolver'), \$model, \$user);\n"
                            : "        return call_user_func(config('tameng.policies.ownership.resolver'), \$model, \$user);\n";
                    } else {
                        $body .= $hasAfter
                            ? "        \$result = \$model->{$foreignKey} === \$user->id;\n"
                            : "        return \$model->{$foreignKey} === \$user->id;\n";
                    }
                } else {
                    $body .= $hasAfter
                        ? "        \$result = \$user->can('{$permission}');\n"
                        : "        return \$user->can('{$permission}');\n";
                }

                if ($hasAfter) {
                    $modelArg = $isSingleParam ? 'null' : '$model';
                    $body .= "\n        return call_user_func(config('tameng.policies.after'), \$user, '{$action}', {$modelArg}, \$result);\n";
                }

                return <<<PHP
    public function {$method}({$param}): bool
    {
{$body}    }
PHP;
            })
            ->implode("\n\n");

        $imports = $this->buildImports($namespace, array_filter([$userModel, $roleModel]));

        $stub = Str::replace(
            ['{namespace}', '{class}', '{imports}', '{methods}'],
            [$namespace, $className, $imports, $methodsContent],
            $files->get($this->policyStubPath()),
        );

        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, $stub);

        $this->components->twoColumnDetail($className, '<fg=green>written</>');
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

    protected function buildImports(string $policyNamespace, array $modelClasses): string
    {
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
