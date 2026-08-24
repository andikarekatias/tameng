<?php

namespace Andika\Tameng\Tests\Fixtures;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }
}
