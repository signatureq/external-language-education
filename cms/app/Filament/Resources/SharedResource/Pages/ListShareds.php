<?php

namespace App\Filament\Resources\SharedResource\Pages;

use App\Filament\Resources\SharedResource;
use Filament\Resources\Pages\ListRecords;

class ListShareds extends ListRecords
{
    protected static string $resource = SharedResource::class;

    public function getSubheading(): ?string
    {
        return 'Выберите раздел, измените содержание и проверьте черновик перед публикацией.';
    }
}
