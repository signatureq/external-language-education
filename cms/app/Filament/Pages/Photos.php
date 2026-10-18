<?php

namespace App\Filament\Pages;

use App\Models\Media;
use App\Services\MediaUploader;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class Photos extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static string|\UnitEnum|null $navigationGroup = 'Содержание сайта';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Фотографии';

    protected static ?string $title = 'Фотографии';

    protected string $view = 'filament.pages.photos';

    public function getSubheading(): ?string
    {
        return 'Загрузите фотографию, дайте ей понятное название, затем выберите её в нужном блоке страницы. Загрузка сама по себе не меняет сайт.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')->label('Добавить фотографию')->icon('heroicon-o-plus')->schema([
                FileUpload::make('photo')->label('Фотография')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(8192)->storeFiles(false)->required()->helperText('JPG, PNG или WebP, до 8 МБ. Лучше использовать горизонтальные снимки шириной от 1600 пикселей.'),
                TextInput::make('name')->label('Название для вас')->required()->maxLength(160)->placeholder('Например, занятие со взрослыми')->helperText('Это название поможет найти фотографию в редакторе. Посетители его не увидят.'),
                Textarea::make('alt')->label('Описание изображения')->rows(2)->maxLength(500)->helperText('Кратко опишите, что на снимке. Описание можно перенести в поле фотографии при редактировании страницы.'),
            ])->modalHeading('Новая фотография')->modalSubmitActionLabel('Загрузить')->action(function (array $data) {
                app(MediaUploader::class)->store($data['photo'], $data['name'], $data['alt'] ?? '');
                Notification::make()->success()->title('Фотография загружена')->body('Теперь её можно выбрать в редакторе страницы.')->send();
            }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table->modelLabel('фотография')->pluralModelLabel('фотографии')->query(Media::query())->columns([
            ImageColumn::make('path')->label('Фото')->disk('site')->imageHeight(64)->imageWidth(96),
            TextColumn::make('name')->label('Название')->searchable()->wrap()->weight('medium'),
            TextColumn::make('width')->label('Размер')->formatStateUsing(fn (Media $record) => $record->width.' × '.$record->height)->toggleable(),
            TextColumn::make('created_at')->label('Добавлено')->date('d.m.Y')->toggleable(),
        ])->recordActions([
            Action::make('rename')->label('Изменить подпись')->icon('heroicon-o-pencil-square')->fillForm(fn (Media $record) => $record->only(['name', 'alt']))->schema([
                TextInput::make('name')->label('Название для вас')->required()->maxLength(160), Textarea::make('alt')->label('Описание изображения')->maxLength(500),
            ])->modalSubmitActionLabel('Сохранить')->action(fn (Media $record, array $data) => $record->update($data)),
        ])->defaultSort('id', 'desc')->paginated([12, 24, 48])->emptyStateHeading('Добавьте первую фотографию')->emptyStateDescription('После загрузки вы сможете выбрать её на любой странице сайта.');
    }
}
