<?php

use Filament\Pages\Dashboard;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;

return [

    'super_admin' => [
        'enabled' => false,
        'name' => 'super_admin',
    ],

    'slug' => 'tameng',

    'permission' => [
        'separator' => '_',
        'case' => 'snake',
        'generate' => true,
        'name_max_length' => 255,
    ],

    'custom_permissions' => [
        //
    ],

    'resources' => [
        'subject' => 'model',
        'exclude' => [
            //
        ],
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
    ],

    'register_role_policy' => true,

    'tenant_model' => null,

    'entity_discovery' => true,

    'localization' => [
        'enabled' => false,
        'key' => 'tameng::tameng.permissions',
    ],

];
