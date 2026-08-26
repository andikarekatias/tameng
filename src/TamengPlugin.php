<?php

declare(strict_types=1);

namespace Andika\Tameng;

use Andika\Tameng\Filament\Resources\RoleResource;
use Andika\Tameng\Http\Middleware\SyncTenant;
use BackedEnum;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;

class TamengPlugin implements Plugin
{
    protected ?string $superAdminRole = null;

    protected ?bool $entityDiscovery = null;

    protected ?string $navigationGroup = null;

    protected ?string $navigationLabel = null;

    protected BackedEnum|string|null $navigationIcon = null;

    protected ?int $navigationSort = null;

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

    public function navigationGroup(string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function navigationLabel(string $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function navigationIcon(Heroicon|string|null $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

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

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup ?? (string) config('tameng.navigation.group', 'Access');
    }

    public function getNavigationLabel(): string
    {
        return $this->navigationLabel ?? (string) config('tameng.navigation.label', 'Tameng');
    }

    public function getNavigationIcon(): BackedEnum|string|null
    {
        return $this->navigationIcon ?? config('tameng.navigation.icon', Heroicon::ShieldCheck);
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort ?? config('tameng.navigation.sort');
    }
}
