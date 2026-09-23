<?php

declare(strict_types=1);

namespace Andika\Tameng\Commands;

use Andika\Tameng\Support\ModelHelper;
use Andika\Tameng\Support\PermissionHelper;
use Andika\Tameng\TamengPlugin;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\Resources\Pages\Page as ResourcePage;
use Illuminate\Console\Command;

class CheckPermissionsCommand extends Command
{
    public $signature = 'tameng:check {--panel=}';

    public $description = 'Validate that all permissions are consistent across policies and Filament panels';

    public function handle(): int
    {
        $separator = (string) config('tameng.permission.separator', '_');
        $case = (string) config('tameng.permission.case', 'snake');
        $methods = (array) config('tameng.policies.methods', []);
        $subject = (string) config('tameng.resources.subject', 'model');
        $issues = [];
        $checked = 0;

        $panels = $this->panels();

        $permissionModel = ModelHelper::permissionModelClass();
        $definedPermissions = $permissionModel::pluck('name')->toArray();

        foreach ($panels as $panel) {
            if (! TamengPlugin::forPanel($panel)->shouldDiscoverEntities()) {
                continue;
            }

            $guard = $panel->getAuthGuard();

            $exclude = array_map('strval', (array) config('tameng.resources.exclude', []));

            foreach ($panel->getResources() as $resource) {
                if (in_array($resource, $exclude, true)) {
                    continue;
                }

                $entity = PermissionHelper::entityName($resource, $subject);

                foreach ($methods as $action) {
                    $expectedPermission = PermissionHelper::permissionName($entity, $action, $separator, $case);
                    $checked++;

                    if (! in_array($expectedPermission, $definedPermissions, true)) {
                        $issues[] = "{$resource} expects permission \"{$expectedPermission}\" — not defined";
                    }
                }
            }

            $pageSubject = (string) config('tameng.pages.subject', 'class');
            $pageExclude = array_map('strval', (array) config('tameng.pages.exclude', []));

            foreach ($panel->getPages() as $page) {
                if (is_a($page, ResourcePage::class, true) || is_a($page, Dashboard::class, true)) {
                    continue;
                }

                if (in_array($page, $pageExclude, true)) {
                    continue;
                }

                $entity = PermissionHelper::entityName($page, $pageSubject);
                $expectedPermission = PermissionHelper::permissionName($entity, 'view', $separator, $case);
                $checked++;

                if (! in_array($expectedPermission, $definedPermissions, true)) {
                    $issues[] = "{$page} expects permission \"{$expectedPermission}\" — not defined";
                }
            }

            $widgetSubject = (string) config('tameng.widgets.subject', 'class');
            $widgetExclude = array_map('strval', (array) config('tameng.widgets.exclude', []));

            foreach ($panel->getWidgets() as $widget) {
                $class = is_object($widget) ? $widget::class : $widget;

                if (in_array($class, $widgetExclude, true)) {
                    continue;
                }

                $entity = PermissionHelper::entityName($class, $widgetSubject);
                $expectedPermission = PermissionHelper::permissionName($entity, 'view', $separator, $case);
                $checked++;

                if (! in_array($expectedPermission, $definedPermissions, true)) {
                    $issues[] = "{$class} expects permission \"{$expectedPermission}\" — not defined";
                }
            }
        }

        $this->components->twoColumnDetail('Permissions checked', (string) $checked);

        if ($issues === []) {
            $this->info('✓ All permissions are consistent.');

            return self::SUCCESS;
        }

        foreach ($issues as $issue) {
            $this->error("✗ {$issue}");
        }

        return self::FAILURE;
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
}
