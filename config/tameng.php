<?php

use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;

return [

    'super_admin' => [
        'enabled' => false,
        'name' => 'super_admin',
    ],

    'slug' => 'tameng',

    'navigation' => [
        'group' => 'Access',
        'label' => 'Tameng',
        'icon' => Heroicon::ShieldCheck,
        'sort' => null,
    ],

    'permission' => [
        'separator' => '_',
        'case' => 'snake',
        'generate' => true,
        'name_max_length' => 255,
        'scoped_to_panel' => false,
        'enforce_page_permissions' => true,
    ],

    'custom_permissions' => [],

    'resources' => [
        'subject' => 'model',
        'exclude' => [],
        'generate_can_view_any' => false,
    ],

    'pages' => [
        'subject' => 'class',
        'exclude' => [
            Dashboard::class,
        ],
    ],

    'widgets' => [
        'subject' => 'class',
        'exclude' => [
            AccountWidget::class,
            FilamentInfoWidget::class,
        ],
    ],

    'policies' => [
        'path' => app_path('Policies'),
        'namespace' => 'App\\Policies',
        'methods' => [
            'view_any',
            'view',
            'create',
            'update',
            'delete',
            'delete_any',
            'restore',
            'restore_any',
            'force_delete',
            'force_delete_any',
        ],
        'single_parameter_methods' => [
            'view_any',
            'create',
            'delete_any',
            'restore_any',
            'force_delete_any',
        ],
        'ownership' => [
            'enabled' => true,
            'foreign_key' => 'user_id',
            'resolver' => null,
        ],
        'before' => null,
        'after' => null,
    ],

    'register_role_policy' => true,

    'tenant_model' => null,

    'entity_discovery' => true,

    'localization' => [
        'enabled' => false,
        'key' => 'tameng::tameng.permissions',
    ],

];
