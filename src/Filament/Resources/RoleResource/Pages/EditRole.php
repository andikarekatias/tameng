<?php

declare(strict_types=1);

namespace Andika\Tameng\Filament\Resources\RoleResource\Pages;

use Andika\Tameng\Filament\Resources\RoleResource;
use Andika\Tameng\Support\Concerns\SyncsPermissions;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    use SyncsPermissions;

    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['guard_name'] = $data['guard_name'] ?: config('auth.defaults.guard');

        return $data;
    }

    protected function afterSave(): void
    {
        $this->syncPermissions();
    }
}
