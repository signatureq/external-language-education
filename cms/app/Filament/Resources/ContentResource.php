<?php

namespace App\Filament\Resources;

use App\Models\ContentDocument;
use App\Models\Media;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

abstract class ContentResource extends Resource
{
    protected static ?string $model = ContentDocument::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Содержание сайта';

    public static string $kind = 'page';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('kind', static::$kind);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function editorUrl(ContentDocument $doc): string
    {
        $resource = match ($doc->kind) {
            'language' => LanguageResource::class,'shared' => SharedResource::class,'settings' => SettingsResource::class,default => PageResource::class
        };

        return $resource::getUrl('edit', ['record' => $doc]);
    }

    public static function form(Schema $schema): Schema
    {
        $doc = $schema->getRecord();
        if (! $doc) {
            return $schema->components([]);
        }
        $sections = [Hidden::make('version')];
        foreach ($doc->schema as $i => $group) {
            $fields = [];
            $totals = array_count_values(array_column($group['fields'], 'label'));
            $seen = [];
            foreach ($group['fields'] as $field) {
                $key = 'draft.'.$field['key'];
                $component = match ($field['type']) {
                    'textarea' => Textarea::make($key)->rows(3)->autosize()->maxLength(12000),
                    'image' => Select::make($key)->options(fn () => Media::orderBy('name')->pluck('name', 'path'))->searchable()->native(false)->live()->required(),
                    'number' => TextInput::make($key)->numeric()->integer()->minValue(1)->maxValue(100000)->required(),
                    'time' => TextInput::make($key)->required()->regex('/^([01]?\d|2[0-3]):[0-5]\d–([01]?\d|2[0-3]):[0-5]\d$/')->validationMessages(['regex' => 'Укажите время, например 9:00–13:30. Между временем начала и окончания поставьте тире.']),
                    default => TextInput::make($key)->maxLength(12000),
                };
                $label = $field['label'];
                $seen[$label] = ($seen[$label] ?? 0) + 1;
                if (($totals[$label] ?? 0) > 1) {
                    $label .= ' '.$seen[$label];
                }
                $fields[] = $component->label($label)->helperText($field['hint'] ?? null)->columnSpanFull();
                if ($field['type'] === 'image') {
                    $fields[] = Placeholder::make('photo_'.$field['key'])->label('Выбранная фотография')->content(fn (Get $get) => new HtmlString('<img src="'.e(url('/'.ltrim((string) $get($key), '/'))).'" alt="Выбранная фотография" style="max-height:180px;max-width:100%;border-radius:10px;object-fit:contain">'))->columnSpanFull();
                }
            }
            $sections[] = Section::make($group['title'])->description($group['hint'] ?? 'Тексты расположены в том же порядке, что и на странице сайта.')->schema($fields)->collapsible()->collapsed($i > 0)->columnSpanFull();
        }
        if (in_array($doc->kind, ['page', 'language'])) {
            $sections[] = Section::make('Как страница выглядит в поиске')->description('Это название и описание могут показываться в Яндексе и Google. Адрес страницы менять не нужно.')->schema([
                TextInput::make('draft.seo_title')->label('Название в поиске')->required()->maxLength(180)->helperText('Короткое и точное название, желательно до 60–70 знаков.'),
                Textarea::make('draft.seo_description')->label('Описание в поиске')->required()->maxLength(500)->rows(3)->helperText('О чём эта страница и чем она полезна. Обычно достаточно 120–160 знаков.'),
            ])->collapsible()->collapsed()->columnSpanFull();
        }

        return $schema->components($sections)->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Страница или раздел')->searchable()->wrap()->weight('medium'),
            TextColumn::make('status')->label('Состояние')->badge()->color(fn (string $state) => $state === 'Есть черновик' ? 'warning' : 'success'),
            TextColumn::make('published_at')->label('Опубликовано')->dateTime('d.m.Y H:i')->sortable()->toggleable(),
        ])->recordActions([EditAction::make()->label('Редактировать')])->defaultSort('id')->paginated([10, 25, 50])->emptyStateHeading('Пока нет содержания')->emptyStateDescription('Страницы появятся после первоначального импорта сайта.');
    }
}
