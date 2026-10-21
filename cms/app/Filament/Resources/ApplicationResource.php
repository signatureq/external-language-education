<?php

namespace App\Filament\Resources;

use App\Models\Application;
use App\Services\ApplicationNotifier;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ApplicationResource extends Resource
{
    protected static ?string $model = Application::class;

    protected static ?string $navigationLabel = 'Заявки';

    protected static ?string $modelLabel = 'заявка';

    protected static ?string $pluralModelLabel = 'Заявки';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox';

    protected static string|\UnitEnum|null $navigationGroup = 'Обращения';

    protected static ?int $navigationSort = 1;

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

    public static function getNavigationBadge(): ?string
    {
        $count = Application::where('status', 'new')->count();

        return $count ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Контакт и вопрос')->description('Заявка сохранена с сайта. Свяжитесь с человеком по указанному телефону.')->schema([
                TextEntry::make('name')->label('Имя'),
                TextEntry::make('phone')->label('Телефон')->copyable()->url(fn ($record) => 'tel:'.preg_replace('/[^+0-9]/', '', $record->phone)),
                TextEntry::make('language')->label('Язык'),
                TextEntry::make('preferences')->label('Время и оплата')->placeholder('Не выбраны'),
                TextEntry::make('comment')->label('Вопрос')->placeholder('Комментарий не оставлен')->columnSpanFull(),
                TextEntry::make('created_at')->label('Получена')->dateTime('d.m.Y H:i')->timezone('Europe/Moscow'),
                TextEntry::make('notified_at')->label('Уведомление на почту')->dateTime('d.m.Y H:i')->timezone('Europe/Moscow')->placeholder('Ещё не отправлено — заявка сохранена здесь'),
            ])->columns(2)->columnSpanFull(),
            Section::make('Работа с заявкой')->schema([
                Select::make('status')->label('Статус')->options(Application::STATUSES)->required()->native(false)->helperText('После разговора выберите «Связались», а когда вопрос решён — «Завершена».'),
                TextEntry::make('reference')->label('Номер заявки')->copyable(),
                TextEntry::make('consented_at')->label('Согласие получено')->dateTime('d.m.Y H:i')->timezone('Europe/Moscow'),
            ])->columnSpanFull(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('created_at')->label('Получена')->dateTime('d.m.Y H:i')->timezone('Europe/Moscow')->sortable(),
            TextColumn::make('name')->label('Имя')->searchable()->wrap()->weight('medium'),
            TextColumn::make('phone')->label('Телефон')->searchable()->copyable(),
            TextColumn::make('language')->label('Язык')->wrap(),
            TextColumn::make('status')->label('Статус')->formatStateUsing(fn ($state) => Application::STATUSES[$state] ?? $state)->badge()->color(fn ($state) => match ($state) {
                'new' => 'warning', 'contacted' => 'info', default => 'gray'
            }),
            TextColumn::make('notified_at')->label('Почта')->formatStateUsing(fn () => 'Передано серверу')->placeholder('Ожидает отправки')->toggleable(isToggledHiddenByDefault: true),
        ])->filters([SelectFilter::make('status')->label('Статус')->options(Application::STATUSES)])
            ->recordActions([
                EditAction::make()->label('Открыть'),
                Action::make('resend')->label('Повторить уведомление')->icon('heroicon-o-envelope')->visible(fn ($record) => ! $record->notified_at)
                    ->action(function ($record) {
                        $sent = app(ApplicationNotifier::class)->send($record);
                        Notification::make()->title($sent ? 'Уведомление передано почтовому серверу' : 'Почта пока недоступна. Заявка сохранена в админке')->status($sent ? 'success' : 'warning')->send();
                    }),
            ])->defaultSort('created_at', 'desc')->paginated([10, 25, 50])->emptyStateHeading('Заявок пока нет')->emptyStateDescription('Здесь появятся обращения с сайта. Уведомление о каждой заявке также отправляется на почту школы.');
    }

    public static function getPages(): array
    {
        return ['index' => ApplicationResource\Pages\ListApplications::route('/'), 'edit' => ApplicationResource\Pages\EditApplication::route('/{record}/edit')];
    }
}
