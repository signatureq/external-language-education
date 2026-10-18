<?php

namespace App\Filament\Resources;

class PageResource extends ContentResource
{
    public static string $kind = 'page';

    protected static ?string $navigationLabel = 'Страницы';

    protected static ?string $modelLabel = 'раздел';

    protected static ?string $pluralModelLabel = 'Страницы';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Содержание сайта';

    protected static ?int $navigationSort = 1;

    public static function getPages(): array
    {
        return ['index' => PageResource\Pages\ListPages::route('/'), 'edit' => PageResource\Pages\EditPage::route('/{record}/edit')];
    }
}
