<?php

namespace Andika\Tameng\Tests\Fixtures;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
class User extends Authenticatable
{
    use HasRoles;
}
