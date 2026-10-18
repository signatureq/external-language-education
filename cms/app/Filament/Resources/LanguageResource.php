<?php

namespace App\Filament\Resources;

class LanguageResource extends ContentResource
{
    public static string $kind = 'language';

    protected static ?string $navigationLabel = 'Языки';

    protected static ?string $modelLabel = 'раздел';

    protected static ?string $pluralModelLabel = 'Языки';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-language';

    protected static string|\UnitEnum|null $navigationGroup = 'Содержание сайта';

    protected static ?int $navigationSort = 2;

    public static function getPages(): array
    {
        return ['index' => LanguageResource\Pages\ListLanguages::route('/'), 'edit' => LanguageResource\Pages\EditLanguage::route('/{record}/edit')];
    }
}
