<?php

namespace App\Filament\Resources\SettingsResource\Pages;

use App\Filament\Resources\SettingsResource;
use Filament\Resources\Pages\ListRecords;

class ListSettingss extends ListRecords
{
    protected static string $resource = SettingsResource::class;

    public function getSubheading(): ?string
    {
        return 'Выберите раздел, измените содержание и проверьте черновик перед публикацией.';
    }
}
