<?php

namespace Andika\Tameng\Tests\Fixtures;

use BackedEnum;
use Filament\Pages\Page;

class MediaLibraryPage extends Page
{
    protected static BackedEnum | string | null $navigationIcon = 'heroicon-o-photo';

    protected static ?string $slug = 'media-library';

    protected static ?string $title = 'Media Library';
}
