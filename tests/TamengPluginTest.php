<?php

use Andika\Tameng\TamengPlugin;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;

it('registers plugin with default configuration', function () {
    $plugin = TamengPlugin::make();

    expect($plugin->getId())->toBe('tameng')
        ->and($plugin->getSuperAdminRole())->toBe('super_admin')
        ->and($plugin->getNavigationGroup())->toBe('Access')
        ->and($plugin->getNavigationLabel())->toBe('Tameng')
        ->and($plugin->getNavigationIcon())->toBe(Heroicon::ShieldCheck)
        ->and($plugin->getNavigationSort())->toBeNull();
});

it('supports fluent configuration overrides', function () {
    $plugin = TamengPlugin::make()
        ->superAdminRole('root')
        ->navigationGroup('System Settings')
        ->navigationLabel('Access Control')
        ->navigationIcon(Heroicon::Key)
        ->navigationSort(5)
        ->entityDiscovery(false);

    expect($plugin->getSuperAdminRole())->toBe('root')
        ->and($plugin->getNavigationGroup())->toBe('System Settings')
        ->and($plugin->getNavigationLabel())->toBe('Access Control')
        ->and($plugin->getNavigationIcon())->toBe(Heroicon::Key)
        ->and($plugin->getNavigationSort())->toBe(5)
        ->and($plugin->shouldDiscoverEntities())->toBeFalse();
});
