<?php

namespace App\Filament\Resources\ApplicationResource\Pages;

use App\Filament\Resources\ApplicationResource;
use Filament\Resources\Pages\ListRecords;

class ListApplications extends ListRecords
{
    protected static string $resource = ApplicationResource::class;

    public function getSubheading(): ?string
    {
        return 'Обращения с сайта. Новые заявки сверху; откройте заявку, чтобы прочитать вопрос и отметить результат разговора.';
    }
}
