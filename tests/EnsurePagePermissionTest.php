<?php

use Andika\Tameng\Http\Middleware\EnsurePagePermission;
use Andika\Tameng\Tests\Fixtures\MediaLibraryPage;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('skips non-page routes', function () {
    $middleware = new EnsurePagePermission;

    $request = Request::create('/test', 'GET');
    $route = new Route('GET', '/test', ['uses' => 'SomeController@index']);
    $request->setRouteResolver(fn () => $route);

    $response = $middleware->handle($request, fn ($req) => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('aborts when user lacks permission for standalone page', function () {
    $middleware = new EnsurePagePermission;

    $request = Request::create('/admin/media-library', 'GET');
    $route = new Route('GET', '/admin/media-library', ['uses' => MediaLibraryPage::class]);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => new Response('ok'));
})->throws(HttpException::class);
