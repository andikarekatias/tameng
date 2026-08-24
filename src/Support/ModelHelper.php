<?php

declare(strict_types=1);

namespace Andika\Tameng\Support;

use Illuminate\Database\Eloquent\Model;

final class ModelHelper
{
    public static function permissionModelClass(): string
    {
        /** @var class-string<Model> $model */
        $model = config('permission.models.permission');

        return $model;
    }

    public static function roleModelClass(): string
    {
        /** @var class-string<Model> $model */
        $model = config('permission.models.role');

        return $model;
    }
}
