<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    protected $guarded = [];

    public const STATUSES = ['new' => 'Новая', 'contacted' => 'Связались', 'closed' => 'Завершена'];

    public const LANGUAGES = ['Итальянский', 'Английский', 'Французский', 'Испанский', 'Китайский', 'Корейский', 'Немецкий', 'Помогите выбрать'];

    protected function casts(): array
    {
        return ['consented_at' => 'datetime', 'notified_at' => 'datetime', 'notification_attempted_at' => 'datetime', 'consent_documents' => 'array'];
    }
}
