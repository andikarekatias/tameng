<?php

declare(strict_types=1);

namespace Andika\Tameng\Support\Concerns;

use Andika\Tameng\Filament\Resources\RoleResource;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

trait SyncsPermissions
{
    protected function syncPermissions(): void
    {
        /** @var Model $record */
        $record = $this->record;
        $formData = $this->data;

        if (! method_exists($record, 'syncPermissions')) {
            return;
        }

        $panel = Filament::getCurrentPanel();
        $subject = (string) config('tameng.resources.subject', 'model');
        $exclude = array_map('strval', (array) config('tameng.resources.exclude', []));

        $resourceEntities = collect($panel?->getResources() ?? [])
            ->filter(fn (string $resource): bool => ! in_array($resource, $exclude, true))
            ->map(fn (string $resource) => RoleResource::entityName($resource, $subject))
            ->values()
            ->all();

        $permissionNames = collect();

        foreach ($resourceEntities as $entity) {
            if (isset($formData[$entity]) && is_array($formData[$entity])) {
                $permissionNames = $permissionNames->merge($formData[$entity]);
            }
        }

        foreach ($formData as $key => $value) {
            if (str_starts_with($key, RoleResource::PAGE_PREFIX) && is_array($value)) {
                $permissionNames = $permissionNames->merge($value);
            }

            if (str_starts_with($key, RoleResource::WIDGET_PREFIX) && is_array($value)) {
                $permissionNames = $permissionNames->merge($value);
            }
        }

        if (isset($formData['custom_permissions']) && is_array($formData['custom_permissions'])) {
            $permissionNames = $permissionNames->merge($formData['custom_permissions']);
        }

        $permissionNames = $permissionNames->values()->all();

        $record->syncPermissions($permissionNames);
    }
}
