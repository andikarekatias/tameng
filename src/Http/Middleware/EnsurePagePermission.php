<?php

declare(strict_types=1);

namespace Andika\Tameng\Http\Middleware;

use Andika\Tameng\Support\PermissionHelper;
use Closure;
use Filament\Pages\Dashboard;
use Filament\Pages\Page;
use Filament\Resources\Pages\Page as ResourcePage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsurePagePermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $pageClass = $route->getControllerClass();

        if ($pageClass === null && $uses = $route->getAction('uses')) {
            $pageClass = explode('@', $uses)[0];
        }

        if (
            $pageClass
            && is_subclass_of($pageClass, Page::class)
            && ! is_subclass_of($pageClass, ResourcePage::class)
            && $pageClass !== Dashboard::class
        ) {
            // Skip if page already defines its own canAccess()
            $reflection = new \ReflectionMethod($pageClass, 'canAccess');
            if ($reflection->getDeclaringClass()->getName() !== Page::class) {
                return $next($request);
            }

            $permission = PermissionHelper::permissionName(
                PermissionHelper::entityName($pageClass, 'class'),
                'view',
                (string) config('tameng.permission.separator', '_'),
                (string) config('tameng.permission.case', 'snake'),
            );

            if (! Gate::allows($permission)) {
                abort(403, "Permission [{$permission}] required.");
            }
        }

        return $next($request);
    }
}
