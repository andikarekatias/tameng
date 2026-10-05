<?php

declare(strict_types=1);

namespace Andika\Tameng\Support\Concerns;

use Andika\Tameng\Support\PermissionHelper;
use Filament\Facades\Filament;

/** @phpstan-ignore trait.unused */
trait HasPermissionCheck
{
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        $entity = PermissionHelper::entityName(
            static::class,
            (string) config('tameng.resources.subject', 'model')
        );

        $separator = (string) config('tameng.permission.separator', '_');
        $case = (string) config('tameng.permission.case', 'snake');
        $panelId = Filament::getCurrentPanel()?->getId();
        $permission = PermissionHelper::permissionName($entity, 'view_any', $separator, $case, $panelId);

        return $user->can($permission);
    }
}
