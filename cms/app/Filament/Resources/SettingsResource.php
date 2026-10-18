<?php

namespace App\Filament\Resources;

class SettingsResource extends ContentResource
{
    public static string $kind = 'settings';

    protected static ?string $navigationLabel = 'Стоимость и расписание';

    protected static ?string $modelLabel = 'раздел';

    protected static ?string $pluralModelLabel = 'Стоимость и расписание';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|\UnitEnum|null $navigationGroup = 'Школа';

    protected static ?int $navigationSort = 4;

    public static function getPages(): array
    {
        return ['index' => SettingsResource\Pages\ListSettingss::route('/'), 'edit' => SettingsResource\Pages\EditSettings::route('/{record}/edit')];
    }
}
