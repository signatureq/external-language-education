<?php

namespace App\Filament\Resources\LanguageResource\Pages;

use App\Filament\Resources\LanguageResource;
use Filament\Resources\Pages\ListRecords;

class ListLanguages extends ListRecords
{
    protected static string $resource = LanguageResource::class;

    public function getSubheading(): ?string
    {
        return 'Выберите раздел, измените содержание и проверьте черновик перед публикацией.';
    }
}
