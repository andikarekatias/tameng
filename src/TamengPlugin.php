<?php

declare(strict_types=1);

namespace Andika\Tameng;

use Andika\Tameng\Filament\Resources\RoleResource;
use Andika\Tameng\Http\Middleware\SyncTenant;
use Filament\Contracts\Plugin;
use Filament\Panel;

class TamengPlugin implements Plugin
{
    protected ?string $superAdminRole = null;

    protected ?bool $entityDiscovery = null;

    public function getId(): string
    {
        return 'tameng';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            RoleResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        if (config('permission.teams')) {
            $panel->tenantMiddleware([SyncTenant::class]);
        }
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public static function forPanel(Panel $panel): static
    {
        $plugin = collect($panel->getPlugins())
            ->first(fn (Plugin $plugin): bool => $plugin->getId() === static::make()->getId());

        return $plugin instanceof static ? $plugin : static::make();
    }

    public function superAdminRole(string $role): static
    {
        $this->superAdminRole = $role;

        return $this;
    }

    public function entityDiscovery(bool $enabled = true): static
    {
        $this->entityDiscovery = $enabled;

        return $this;
    }

    public function getSuperAdminRole(): string
    {
        return $this->superAdminRole ?? (string) config('tameng.super_admin.name', 'super_admin');
    }

    public function shouldDiscoverEntities(): bool
    {
        return $this->entityDiscovery ?? (bool) config('tameng.entity_discovery');
    }
}
