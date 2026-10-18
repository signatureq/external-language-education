<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Help extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static string|\UnitEnum|null $navigationGroup = 'Помощь';

    protected static ?string $navigationLabel = 'Как редактировать сайт';

    protected static ?string $title = 'Как редактировать сайт';

    protected string $view = 'filament.pages.help';
}
