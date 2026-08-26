<?php

declare(strict_types=1);

namespace Andika\Tameng\Filament\Resources;

use Andika\Tameng\Filament\Resources\RoleResource\Pages\CreateRole;
use Andika\Tameng\Filament\Resources\RoleResource\Pages\EditRole;
use Andika\Tameng\Filament\Resources\RoleResource\Pages\ListRoles;
use Andika\Tameng\Support\ModelHelper;
use Andika\Tameng\Support\PermissionHelper;
use Andika\Tameng\TamengPlugin;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component as SchemaComponent;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use Spatie\Permission\Contracts\Role as RoleContract;

class RoleResource extends Resource
{
    public const PAGE_PREFIX = 'pages_';

    public const WIDGET_PREFIX = 'widgets_';

    public static function getModel(): string
    {
        return ModelHelper::roleModelClass();
    }

    public static function getModelLabel(): string
    {
        return 'Role';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Roles';
    }

    public static function getNavigationLabel(): string
    {
        try {
            return TamengPlugin::get()->getNavigationLabel();
        } catch (\Throwable) {
            return (string) config('tameng.navigation.label', 'Tameng');
        }
    }

    public static function getNavigationGroup(): ?string
    {
        try {
            return TamengPlugin::get()->getNavigationGroup();
        } catch (\Throwable) {
            return (string) config('tameng.navigation.group', 'Access');
        }
    }

    public static function getNavigationIcon(): BackedEnum | string | null
    {
        try {
            return TamengPlugin::get()->getNavigationIcon();
        } catch (\Throwable) {
            return config('tameng.navigation.icon');
        }
    }

    public static function getNavigationSort(): ?int
    {
        try {
            return TamengPlugin::get()->getNavigationSort();
        } catch (\Throwable) {
            return config('tameng.navigation.sort');
        }
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return (string) config('tameng.slug', 'tameng');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (config('permission.teams') && Filament::hasTenancy()) {
            $tenant = Filament::getTenant();

            if ($tenant) {
                $query->where(config('permission.column_names.team_foreign_key'), $tenant->getKey());
            }
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()
                    ->schema([
                        Section::make()
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->unique(ignoreRecord: true, modifyRuleUsing: function (Unique $rule, SchemaComponent $component) {
                                        $record = $component->getRecord();
                                        $guard = $record instanceof Model ? $record->getAttributeValue('guard_name') : config('auth.defaults.guard');

                                        return $rule->where('guard_name', $guard);
                                    })
                                    ->maxLength((int) config('tameng.permission.name_max_length', 255)),
                                Select::make('guard_name')
                                    ->options(fn () => collect(config('auth.guards', []))->mapWithKeys(fn (array $config, string $name) => [$name => $name])->all())
                                    ->afterStateHydrated(function (SchemaComponent $component): void {
                                        $record = $component->getRecord();
                                        if ($record !== null && is_null($component->getState())) {
                                            $component->state($record->getAttributeValue('guard_name'));
                                        }
                                    })
                                    ->required(),
                            ])
                            ->columns(['sm' => 1, 'lg' => 2])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                static::getPermissionCardsFormComponent(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('guard_name'),
                TextColumn::make('permissions_count')
                    ->label('Permissions')
                    ->state(fn (RoleContract $record): int => $record->permissions()->count())
                    ->badge(),
            ])
            ->filters([])
            ->actions([EditAction::make(), DeleteAction::make()])
            ->bulkActions([]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }

    public static function getPermissionCardsFormComponent(): Grid
    {
        $cards = collect()
            ->merge(static::getResourceCards())
            ->merge(static::getPageCards())
            ->merge(static::getWidgetCards())
            ->when(static::getCustomCard() !== null, fn ($c) => $c->push(static::getCustomCard()))
            ->values()
            ->all();

        return Grid::make(3)->schema($cards)->columnSpanFull();
    }

    public static function getResourceCards(): array
    {
        $panel = Filament::getCurrentPanel();
        $guard = $panel?->getAuthGuard() ?? config('auth.defaults.guard');
        $separator = (string) config('tameng.permission.separator', '_');
        $case = (string) config('tameng.permission.case', 'snake');
        $methods = (array) config('tameng.policies.methods', []);
        $subject = (string) config('tameng.resources.subject', 'model');
        $exclude = array_map('strval', (array) config('tameng.resources.exclude', []));

        $entities = collect($panel?->getResources() ?? [])
            ->filter(fn (string $resource): bool => ! in_array($resource, $exclude, true))
            ->mapWithKeys(fn (string $resource): array => [PermissionHelper::entityName($resource, $subject) => true])
            ->keys()
            ->all();

        $allPermissions = collect($entities)
            ->flatMap(fn (string $entity) => collect($methods)
                ->map(fn (string $action) => PermissionHelper::permissionName($entity, $action, $separator, $case)))
            ->all();

        $existingPermissions = static::getExistingPermissions($allPermissions, $guard);

        return collect($entities)
            ->mapWithKeys(function (string $entity) use ($methods, $separator, $case, $existingPermissions): array {
                $permissions = collect($methods)
                    ->map(fn (string $action) => PermissionHelper::permissionName($entity, $action, $separator, $case))
                    ->filter(fn (string $name) => in_array($name, $existingPermissions, true))
                    ->mapWithKeys(fn (string $name) => [$name => PermissionHelper::permissionLabel($name)])
                    ->all();

                return [$entity => $permissions];
            })
            ->filter(fn (array $permissions) => $permissions !== [])
            ->map(fn (array $permissions, string $entity) => static::makePermissionCard(
                name: $entity,
                label: Str::headline($entity),
                options: $permissions,
                icon: Heroicon::Cube,
                iconColor: 'primary',
            ))
            ->values()
            ->all();
    }

    public static function getPageCards(): array
    {
        $panel = Filament::getCurrentPanel();
        $guard = $panel?->getAuthGuard() ?? config('auth.defaults.guard');
        $separator = (string) config('tameng.permission.separator', '_');
        $case = (string) config('tameng.permission.case', 'snake');
        $subject = (string) config('tameng.pages.subject', 'class');
        $exclude = array_map('strval', (array) config('tameng.pages.exclude', []));

        $allPagePermissions = collect($panel?->getPages() ?? [])
            ->filter(fn (string $page): bool => ! is_a($page, ResourcePage::class, true) && ! is_a($page, Dashboard::class, true))
            ->filter(fn (string $page): bool => ! in_array($page, $exclude, true))
            ->map(fn (string $page) => PermissionHelper::permissionName(PermissionHelper::entityName($page, $subject), 'view', $separator, $case))
            ->all();

        $existingPermissions = static::getExistingPermissions($allPagePermissions, $guard);

        return collect($panel?->getPages() ?? [])
            ->filter(fn (string $page): bool => ! is_a($page, ResourcePage::class, true) && ! is_a($page, Dashboard::class, true))
            ->filter(fn (string $page): bool => ! in_array($page, $exclude, true))
            ->mapWithKeys(function (string $page) use ($subject, $separator, $case, $existingPermissions): array {
                $name = PermissionHelper::permissionName(PermissionHelper::entityName($page, $subject), 'view', $separator, $case);

                return in_array($name, $existingPermissions, true)
                    ? [$name => PermissionHelper::permissionLabel($name)]
                    : [];
            })
            ->filter()
            ->map(fn (string $label, string $name) => static::makePermissionCard(
                name: self::PAGE_PREFIX . $name,
                label: $label,
                options: [$name => $label],
                icon: Heroicon::DocumentText,
                iconColor: 'success',
            ))
            ->values()
            ->all();
    }

    public static function getWidgetCards(): array
    {
        $panel = Filament::getCurrentPanel();
        $guard = $panel?->getAuthGuard() ?? config('auth.defaults.guard');
        $separator = (string) config('tameng.permission.separator', '_');
        $case = (string) config('tameng.permission.case', 'snake');
        $subject = (string) config('tameng.widgets.subject', 'class');
        $exclude = array_map('strval', (array) config('tameng.widgets.exclude', []));

        $allWidgetPermissions = collect($panel?->getWidgets() ?? [])
            ->map(fn (mixed $widget): string => is_object($widget) ? $widget::class : $widget)
            ->filter(fn (string $widget): bool => ! in_array($widget, $exclude, true))
            ->map(fn (string $widget) => PermissionHelper::permissionName(PermissionHelper::entityName($widget, $subject), 'view', $separator, $case))
            ->all();

        $existingPermissions = static::getExistingPermissions($allWidgetPermissions, $guard);

        return collect($panel?->getWidgets() ?? [])
            ->map(fn (mixed $widget): string => is_object($widget) ? $widget::class : $widget)
            ->filter(fn (string $widget): bool => ! in_array($widget, $exclude, true))
            ->mapWithKeys(function (string $widget) use ($subject, $separator, $case, $existingPermissions): array {
                $name = PermissionHelper::permissionName(PermissionHelper::entityName($widget, $subject), 'view', $separator, $case);

                return in_array($name, $existingPermissions, true)
                    ? [$name => PermissionHelper::permissionLabel($name)]
                    : [];
            })
            ->filter()
            ->map(fn (string $label, string $name) => static::makePermissionCard(
                name: self::WIDGET_PREFIX . $name,
                label: $label,
                options: [$name => $label],
                icon: Heroicon::Square2Stack,
                iconColor: 'warning',
            ))
            ->values()
            ->all();
    }

    public static function getCustomCard(): ?Section
    {
        $panel = Filament::getCurrentPanel();
        $guard = $panel?->getAuthGuard() ?? config('auth.defaults.guard');
        $customPermissions = (array) config('tameng.custom_permissions', []);

        $existingPermissions = static::getExistingPermissions($customPermissions, $guard);

        $options = collect($customPermissions)
            ->filter(fn (string $name) => in_array($name, $existingPermissions, true))
            ->mapWithKeys(fn (string $name) => [$name => PermissionHelper::permissionLabel($name)])
            ->all();

        if ($options === []) {
            return null;
        }

        return static::makePermissionCard(
            name: 'custom_permissions',
            label: 'Custom',
            options: $options,
            icon: Heroicon::Wrench,
            iconColor: 'gray',
        );
    }

    /** @param  array<string>  $permissionNames */
    protected static function getExistingPermissions(array $permissionNames, string $guard): array
    {
        if ($permissionNames === []) {
            return [];
        }

        $permissionModel = ModelHelper::permissionModelClass();

        return $permissionModel::query()
            ->where('guard_name', $guard)
            ->whereIn('name', $permissionNames)
            ->pluck('name')
            ->all();
    }

    public static function makePermissionCard(string $name, string $label, array $options, Heroicon | string $icon, string $iconColor): Section
    {
        return Section::make($label)
            ->icon($icon)
            ->iconColor($iconColor)
            ->collapsible()
            ->compact()
            ->schema([
                CheckboxList::make($name)
                    ->hiddenLabel()
                    ->options(fn (): array => $options)
                    ->searchable(false)
                    ->live()
                    ->dehydrated(false)
                    ->afterStateHydrated(function (SchemaComponent $component, string $operation, ?Model $record) use ($options): void {
                        if (in_array($operation, ['edit', 'view'], true) && $record !== null && method_exists($record, 'checkPermissionTo')) {
                            $component->state(
                                collect(array_keys($options))
                                    ->filter(fn (string $permission) => $record->checkPermissionTo($permission))
                                    ->values()
                                    ->all()
                            );
                        }
                    })
                    ->bulkToggleable()
                    ->gridDirection('row')
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function entityName(string $class, string $subject = 'class'): string
    {
        return PermissionHelper::entityName($class, $subject);
    }

    public static function permissionName(string $entity, string $action, string $separator, string $case): string
    {
        return PermissionHelper::permissionName($entity, $action, $separator, $case);
    }

    public static function permissionLabel(string $name): string
    {
        return PermissionHelper::permissionLabel($name);
    }
}
