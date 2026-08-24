<?php

declare(strict_types=1);

namespace Andika\Tameng\Filament\Resources\RoleResource\Pages;

use Andika\Tameng\Filament\Resources\RoleResource;
use Andika\Tameng\Support\Concerns\SyncsPermissions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    use SyncsPermissions;

    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['guard_name'] = $data['guard_name'] ?: config('auth.defaults.guard');

        if (config('permission.teams') && Filament::hasTenancy()) {
            $tenant = Filament::getTenant();

            if ($tenant) {
                $data[config('permission.column_names.team_foreign_key')] = $tenant->getKey();
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->syncPermissions();
    }
}
