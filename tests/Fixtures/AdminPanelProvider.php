<?php

namespace Andika\Tameng\Tests\Fixtures;

use Andika\Tameng\TamengPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->default()
            ->resources([UserResource::class])
            ->plugin(TamengPlugin::make());
    }
}
