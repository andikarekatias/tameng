<?php

use Andika\Tameng\Support\Concerns\HasPermissionCheck;
use Andika\Tameng\Tests\Fixtures\User;

class DummyResource
{
    use HasPermissionCheck;
}

it('returns false when user is not authenticated', function () {
    auth()->logout();

    expect(DummyResource::canViewAny())->toBeFalse();
});

it('checks can view any permission on authenticated user', function () {
    $user = new User;
    $this->actingAs($user);

    expect(DummyResource::canViewAny())->toBeFalse();
});
