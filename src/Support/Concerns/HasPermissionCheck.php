<?php

declare(strict_types=1);

namespace Andika\Tameng\Support\Concerns;

use Andika\Tameng\Support\PermissionHelper;

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
        $permission = PermissionHelper::permissionName($entity, 'view_any', $separator, $case);

        return $user->can($permission);
    }
}
