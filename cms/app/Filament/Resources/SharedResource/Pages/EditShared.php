<?php

namespace App\Filament\Resources\SharedResource\Pages;

use App\Filament\Resources\ContentResource\Pages\EditContent;
use App\Filament\Resources\SharedResource;

class EditShared extends EditContent
{
    protected static string $resource = SharedResource::class;
}
