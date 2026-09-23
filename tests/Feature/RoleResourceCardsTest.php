<?php

use Andika\Tameng\Filament\Resources\RoleResource;
use Filament\Facades\Filament;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanels()['admin']);
    $this->artisan('tameng:generate')->assertExitCode(0);
});

it('shows a notice when no permissions exist yet', function () {
    config(['tameng.permission.generate' => false]);
    config('permission.models.permission')::query()->delete();

    $cards = RoleResource::getResourceCards();

    expect($cards)->toHaveCount(1);
    expect($cards[0]->getHeading())->toBe('No permissions found');
});

it('shows a notice for pages when no permissions exist', function () {
    config('permission.models.permission')::query()->delete();

    $cards = RoleResource::getPageCards();

    expect($cards)->toHaveCount(1);
    expect($cards[0]->getHeading())->toBe('No permissions found');
});

it('shows a notice for widgets when no permissions exist', function () {
    config('permission.models.permission')::query()->delete();

    $cards = RoleResource::getWidgetCards();

    expect($cards)->toHaveCount(1);
    expect($cards[0]->getHeading())->toBe('No permissions found');
});

it('does not show the notice when permissions exist', function () {
    $cards = RoleResource::getResourceCards();

    expect($cards)->not->toBeEmpty();
    expect($cards[0]->getHeading())->not->toBe('No permissions found');
});
