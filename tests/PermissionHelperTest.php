<?php

use Andika\Tameng\Support\PermissionHelper;
use Andika\Tameng\Tests\Fixtures\MediaLibraryPage;
use Andika\Tameng\Tests\Fixtures\User;
use Andika\Tameng\Tests\Fixtures\UserResource;

it('formats segments across different casing styles', function () {
    expect(PermissionHelper::formatSegment('UserProfile', 'snake'))->toBe('user_profile')
        ->and(PermissionHelper::formatSegment('UserProfile', 'kebab'))->toBe('user-profile')
        ->and(PermissionHelper::formatSegment('user_profile', 'pascal'))->toBe('UserProfile')
        ->and(PermissionHelper::formatSegment('user_profile', 'camel'))->toBe('userProfile')
        ->and(PermissionHelper::formatSegment('UserProfile', 'upper_snake'))->toBe('USER_PROFILE')
        ->and(PermissionHelper::formatSegment('UserProfile', 'lower_snake'))->toBe('user_profile');
});

it('derives entity names correctly', function () {
    expect(PermissionHelper::entityName(UserResource::class, 'model'))->toBe('User')
        ->and(PermissionHelper::entityName(UserResource::class, 'class'))->toBe('User')
        ->and(PermissionHelper::entityName(MediaLibraryPage::class, 'class'))->toBe('MediaLibrary')
        ->and(PermissionHelper::entityName(User::class, 'model'))->toBe('User');
});

it('builds permission names with separator and case', function () {
    expect(PermissionHelper::permissionName('User', 'view_any', '_', 'snake'))->toBe('user_view_any')
        ->and(PermissionHelper::permissionName('User', 'viewAny', ':', 'pascal'))->toBe('User:ViewAny')
        ->and(PermissionHelper::permissionName('UserProfile', 'view', '-', 'kebab'))->toBe('user-profile-view');
});

it('supports panel scoped permission names when enabled', function () {
    config()->set('tameng.permission.scoped_to_panel', true);

    expect(PermissionHelper::permissionName('User', 'view_any', '_', 'snake', 'admin'))->toBe('admin_user_view_any');

    config()->set('tameng.permission.scoped_to_panel', false);

    expect(PermissionHelper::permissionName('User', 'view_any', '_', 'snake', 'admin'))->toBe('user_view_any');
});

it('formats permission labels correctly with and without panel scoping', function () {
    config()->set('tameng.permission.scoped_to_panel', false);
    expect(PermissionHelper::permissionLabel('user_view_any'))->toBe('View Any');

    config()->set('tameng.permission.scoped_to_panel', true);
    expect(PermissionHelper::permissionLabel('admin_user_view_any'))->toBe('View Any');
});

it('resolves model class from resource', function () {
    expect(PermissionHelper::resolveModelClass(UserResource::class))->toBe(User::class);
});
