Новая заявка на обучение — External Language Education
Номер: {{ $application->reference }}
Дата: {{ $application->created_at->timezone('Europe/Moscow')->format('d.m.Y H:i') }} (Москва)

Имя: {{ $application->name }}
Телефон: {{ $application->phone }}
Язык: {{ $application->language }}
@if($application->preferences)
Выбранные условия: {{ $application->preferences }}
@endif

Вопрос:
{{ $application->comment ?: 'Не указан' }}

Страница: https://externlangschool.ru{{ $application->page }}
Согласие на обработку данных получено: {{ $application->consented_at->timezone('Europe/Moscow')->format('d.m.Y H:i') }} (Москва)

Заявка сохранена в админке: {{ rtrim(config('app.url'), '/') }}/admin/applications

Это уведомление с сайта externlangschool.ru. Свяжитесь с человеком по указанному телефону.
