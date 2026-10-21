<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ContentDocument;
use App\Services\ApplicationNotifier;
use App\Services\ApplicationToken;
use App\Services\SiteImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ApplicationController extends Controller
{
    private function origin(Request $request): string
    {
        abort_unless(config('applications.enabled'), 503, 'Приём заявок временно недоступен');
        $origin = (string) $request->header('Origin');
        if ($origin === '' && $request->header('Sec-Fetch-Site') === 'same-origin') {
            $origin = $request->getSchemeAndHttpHost();
        }
        // Also allow the CMS origin for authenticated previews, without sharing sessions.
        $allowed = [...config('applications.origins'), rtrim(config('app.url'), '/')];
        abort_unless(in_array($origin, $allowed, true), 403);

        return $origin;
    }

    public function token(Request $request, ApplicationToken $tokens): JsonResponse
    {
        $origin = $this->origin($request);

        return response()->json(['token' => $tokens->issue($origin)])->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function store(Request $request, ApplicationToken $tokens, ApplicationNotifier $notifier): JsonResponse
    {
        $origin = $this->origin($request);
        abort_if((int) $request->header('Content-Length', 0) > 16384, 413);
        $data = $request->validate([
            'token' => ['required', 'string', 'max:120'],
            'website' => ['nullable', 'string', 'max:200'],
            'name' => ['required', 'string', 'max:80', 'regex:/^[^\x00-\x1F\x7F<>]+$/u'],
            'phone' => ['required', 'string', 'max:25', 'regex:/^\+?[0-9 ()\-]{10,25}$/'],
            'language' => ['required', Rule::in(Application::LANGUAGES)],
            'comment' => ['nullable', 'string', 'max:1000'],
            'preferences' => ['nullable', 'string', 'max:250'],
            'page' => ['required', Rule::in(['/', ...array_map(fn ($file) => '/'.$file, array_keys(SiteImporter::PAGES))])],
            'consent' => ['required', 'accepted'],
        ], [
            'name.required' => 'Напишите, как вас зовут', 'name.regex' => 'Укажите имя без специальных символов',
            'phone.regex' => 'Проверьте номер телефона с кодом страны', 'phone.required' => 'Укажите телефон для связи',
            'language.required' => 'Выберите язык или «Помогите выбрать»', 'language.in' => 'Выберите язык из списка',
            'consent.accepted' => 'Нужно ваше согласие на обработку данных для ответа на обращение',
        ]);
        abort_unless($tokens->valid($data['token'], $origin), 419, 'Форма устарела. Попробуйте отправить ещё раз');
        if (! empty($data['website'])) {
            return response()->json(['accepted' => true, 'reference' => '']);
        }
        $digits = preg_replace('/\D/', '', $data['phone']);
        if (strlen($digits) < 10 || strlen($digits) > 15) {
            throw ValidationException::withMessages(['phone' => 'Укажите от 10 до 15 цифр, включая код страны']);
        }
        $key = hash('sha256', $data['token']);
        $application = Cache::lock('application-submit:'.$key, 10)->block(3, function () use ($data, $key) {
            $documents = ContentDocument::whereIn('key', ['privacy.html', 'consent.html'])->orderBy('key')->get()->mapWithKeys(fn ($doc) => [$doc->key => $doc->published])->all();

            return Application::firstOrCreate(['request_key' => $key], [
                'reference' => strtoupper(bin2hex(random_bytes(6))),
                'name' => $data['name'], 'phone' => $data['phone'], 'language' => $data['language'],
                'comment' => $data['comment'] ?? null, 'preferences' => $data['preferences'] ?? null,
                'page' => $data['page'], 'consented_at' => now(),
                'consent_documents' => $documents,
                'consent_version' => hash('sha256', json_encode($documents, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            ]);
        });
        // Persist before sending: a mail outage must never lose a real application.
        $notifier->send($application);

        return response()->json(['accepted' => true, 'reference' => $application->reference], 201)->header('Cache-Control', 'no-store');
    }
}
