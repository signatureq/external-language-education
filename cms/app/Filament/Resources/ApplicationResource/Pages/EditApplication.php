<?php

namespace App\Filament\Resources\ApplicationResource\Pages;

use App\Filament\Resources\ApplicationResource;
use Filament\Resources\Pages\EditRecord;

class EditApplication extends EditRecord
{
    protected static string $resource = ApplicationResource::class;

    public function getTitle(): string
    {
        return 'Заявка от '.$this->record->name;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ['status' => $data['status']];
    }
}
