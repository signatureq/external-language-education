<?php

namespace App\Filament\Resources\ContentResource\Pages;

use App\Services\ContentEditor;
use App\Services\SitePublisher;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

abstract class EditContent extends EditRecord
{
    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveDraft')->label('Сохранить черновик')->color('gray')->action(fn () => $this->save(false)),
            Action::make('preview')->label('Сохранить и посмотреть')->icon('heroicon-o-eye')->color('gray')->action(function () {
                $this->save(false, false);

                return redirect()->route('content.preview', ['document' => $this->record]);
            }),
            Action::make('publish')->label('Опубликовать')->icon('heroicon-o-arrow-up-tray')->requiresConfirmation()->modalHeading('Опубликовать изменения?')->modalDescription(fn () => in_array($this->record->kind, ['shared', 'settings']) ? 'Правки этого раздела появятся на всех связанных страницах сайта. Другие черновики не будут опубликованы.' : 'Содержимое этой страницы станет доступно посетителям сайта. Сначала посмотрите черновик, если ещё не проверили его.')->modalSubmitActionLabel('Опубликовать')->action(function () {
                $this->save(false, false);
                try {
                    app(SitePublisher::class)->publish($this->record->fresh(), $this->data['version'], auth()->user()->name);
                } catch (\Throwable $e) {
                    report($e);
                    Notification::make()->danger()->title('Публикация не выполнена')->body('Черновик сохранён. Обновите страницу и попробуйте снова. Если ошибка повторится, обратитесь к разработчику.')->persistent()->send();

                    return;
                }
                $this->refreshFormData(['draft', 'version']);
                Notification::make()->title('Изменения опубликованы')->body('Посетители уже видят новую версию.')->success()->send();
            }),
            Action::make('history')->label('История')->icon('heroicon-o-clock')->color('gray')->visible(fn () => $this->record->revisions()->exists())->schema([
                Select::make('revision')->label('Версия для восстановления')->options(fn () => $this->record->revisions()->limit(30)->get()->mapWithKeys(fn ($r) => [$r->id => $r->created_at->format('d.m.Y H:i').' — '.$r->author]))->required()->helperText('Предыдущая опубликованная версия вернётся в черновик. Текущий сайт сохранится до публикации.'),
            ])->modalHeading('Вернуть предыдущую версию')->modalSubmitActionLabel('Вернуть в черновик')->action(function (array $data) {
                $revision = $this->record->revisions()->findOrFail($data['revision']);
                app(ContentEditor::class)->save($this->record, $revision->data, $this->data['version']);
                $this->refreshFormData(['draft', 'version']);
                Notification::make()->title('Версия восстановлена в черновик')->body('Проверьте её в предпросмотре и опубликуйте, когда будете готовы.')->success()->send();
            }),
        ];
    }

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getSubheading(): ?string
    {
        return ($this->record->hasDraft() ? 'Есть сохранённый черновик. Посетители видят прежнюю версию. ' : '').'Сохранение создаёт черновик. Посмотрите результат и нажмите «Опубликовать», когда всё готово.';
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Сохранить черновик');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Черновик сохранён — на сайте пока прежняя версия';
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $fresh = app(ContentEditor::class)->save($record, $data['draft'], (int) $data['version']);
        $record->setRawAttributes($fresh->getAttributes(), true);
        $this->data['version'] = $fresh->version;

        return $record;
    }
}
