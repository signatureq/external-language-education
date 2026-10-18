<?php

namespace App\Filament\Resources;

class SharedResource extends ContentResource
{
    public static string $kind = 'shared';

    protected static ?string $navigationLabel = 'Общие блоки';

    protected static ?string $modelLabel = 'раздел';

    protected static ?string $pluralModelLabel = 'Общие блоки';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string|\UnitEnum|null $navigationGroup = 'Содержание сайта';

    protected static ?int $navigationSort = 3;

    public static function getPages(): array
    {
        return ['index' => SharedResource\Pages\ListShareds::route('/'), 'edit' => SharedResource\Pages\EditShared::route('/{record}/edit')];
    }
}
