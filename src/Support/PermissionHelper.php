<?php

declare(strict_types=1);

namespace Andika\Tameng\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class PermissionHelper
{
    public static function entityName(string $class, string $subject = 'class'): string
    {
        $name = class_basename($class);

        if ($subject === 'model') {
            if (is_a($class, Model::class, true)) {
                $name = class_basename($class);
            } elseif (method_exists($class, 'getModel')) {
                try {
                    /** @var class-string<Model> $modelClass */
                    $modelClass = $class::getModel();
                    $name = class_basename($modelClass);
                } catch (\Throwable) {
                    // Fallback to class name without Resource suffix
                    $name = str_ends_with($name, 'Resource') ? Str::beforeLast($name, 'Resource') : $name;
                }
            }
        } else {
            $name = str_ends_with($name, 'Resource') ? Str::beforeLast($name, 'Resource') : $name;
            $name = str_ends_with($name, 'Page') ? Str::beforeLast($name, 'Page') : $name;
            $name = str_ends_with($name, 'Widget') ? Str::beforeLast($name, 'Widget') : $name;
        }

        return $name;
    }

    public static function permissionName(string $entity, string $action, string $separator, string $case): string
    {
        $entitySegment = self::formatSegment($entity, $case);
        $actionSegment = self::formatSegment($action, $case);

        return "{$entitySegment}{$separator}{$actionSegment}";
    }

    public static function formatSegment(string $value, string $case): string
    {
        return match ($case) {
            'kebab' => str_replace('_', '-', Str::snake($value)),
            'pascal' => Str::studly($value),
            'camel' => Str::camel($value),
            'upper_snake' => Str::upper(Str::snake($value)),
            'lower_snake' => Str::snake($value),
            default => Str::snake($value),
        };
    }

    public static function permissionLabel(string $name): string
    {
        if (config('tameng.localization.enabled')) {
            $key = (string) config('tameng.localization.key') . '.' . $name;
            $label = __($key);

            if ($label !== $key) {
                return $label;
            }
        }

        $separator = (string) config('tameng.permission.separator', '_');
        $parts = explode($separator, $name);
        array_shift($parts);
        $action = implode($separator, $parts);

        return Str::headline($action);
    }
}
