<?php

declare(strict_types=1);

namespace Andika\Tameng\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

class SyncTenant
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (config('permission.teams') && Filament::hasTenancy()) {
            $tenant = Filament::getTenant();

            if ($tenant) {
                $registrar = app(PermissionRegistrar::class);
                $newTeamId = $tenant->getKey();

                if ($registrar->getPermissionsTeamId() !== $newTeamId) {
                    $registrar->setPermissionsTeamId($newTeamId);

                    if ($request->user()) {
                        $request->user()->unsetRelation('roles')->unsetRelation('permissions');
                    }

                    $registrar->forgetCachedPermissions();
                }
            }
        }

        return $next($request);
    }
}
