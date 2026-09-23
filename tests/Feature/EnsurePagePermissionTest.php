<?php

use Andika\Tameng\Http\Middleware\EnsurePagePermission;
use Andika\Tameng\TamengPlugin;
use Andika\Tameng\Tests\Fixtures\MediaLibraryPage;
use Andika\Tameng\Tests\Fixtures\User;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('registers middleware in plugin register so it appears on routes before boot', function () {
    $route = app('router')->getRoutes()->match(
        Request::create('/admin/tameng'),
    );

    $middleware = $route->gatherMiddleware();

    expect($middleware)->toContain(EnsurePagePermission::class);
});

it('boot does not register middleware on the panel', function () {
    config(['tameng.permission.enforce_page_permissions' => true]);

    $panel = Panel::make()->id('test-boot')->path('test-boot');
    (new TamengPlugin)->boot($panel);

    expect($panel->getMiddleware())->not->toContain(EnsurePagePermission::class);
});

it('does not register the middleware when config is disabled', function () {
    config(['tameng.permission.enforce_page_permissions' => false]);

    $panel = Panel::make()->id('test-disabled')->path('test-disabled');
    (new TamengPlugin)->register($panel);

    expect($panel->getMiddleware())->not->toContain(EnsurePagePermission::class);
});

it('registers the middleware on panel when config is enabled', function () {
    config(['tameng.permission.enforce_page_permissions' => true]);

    $panel = Panel::make()->id('test-enabled')->path('test-enabled');
    (new TamengPlugin)->register($panel);

    expect($panel->getMiddleware())->toContain(EnsurePagePermission::class);
});

it('skips permission check for dashboard page', function () {
    $request = Request::create('/admin/dashboard', 'GET');
    $request->setRouteResolver(fn () => new class($request)
    {
        public function __construct(private $request) {}

        public function getControllerClass()
        {
            return Dashboard::class;
        }

        public function getAction($key)
        {
            return $key === 'uses' ? Dashboard::class . '@__invoke' : null;
        }
    });

    $middleware = new EnsurePagePermission;
    $response = $middleware->handle($request, fn ($r) => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('strips invoke suffix from route action when resolving page class', function () {
    $request = Request::create('/admin/media-library', 'GET');
    $request->setRouteResolver(fn () => new class($request)
    {
        public function __construct(private $request) {}

        public function getControllerClass()
        {
            return null;
        }

        public function getAction($key)
        {
            return $key === 'uses' ? MediaLibraryPage::class . '@__invoke' : null;
        }
    });

    $middleware = new EnsurePagePermission;

    try {
        $middleware->handle($request, fn ($r) => new Response('ok'));
        expect(false)->toBeTrue();
    } catch (HttpException $e) {
        expect($e->getMessage())->toContain('media_library_view');
    }
});

it('blocks page without permission', function () {
    config(['tameng.super_admin.enabled' => false]);

    $user = User::create([
        'name' => 'No Permission',
        'email' => 'noperm@example.com',
        'password' => 'secret',
    ]);

    $this->actingAs($user)
        ->get('/admin/media-library')
        ->assertForbidden();
});

it('allows page with permission', function () {
    config(['tameng.super_admin.enabled' => false]);

    Permission::findOrCreate('media_library_view', 'web');

    $user = User::create([
        'name' => 'Has Permission',
        'email' => 'hasperm@example.com',
        'password' => 'secret',
    ]);
    $user->givePermissionTo('media_library_view');

    $this->actingAs($user)
        ->get('/admin/media-library')
        ->assertOk();
});
