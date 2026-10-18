<?php

namespace App\Services;

use App\Models\ContentDocument;
use App\Models\Media;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContentEditor
{
    public function validate(ContentDocument $doc, array $values): array
    {
        $rules = [];
        $attributes = [];
        foreach ($doc->schema as $group) {
            foreach ($group['fields'] as $field) {
                $rule = match ($field['type']) {
                    'number' => ['required', 'integer', 'min:1', 'max:100000'],'time' => ['required', 'regex:/^([01]?\d|2[0-3]):[0-5]\d–([01]?\d|2[0-3]):[0-5]\d$/'],default => ['nullable', 'string', 'max:12000']
                };
                if ($field['type'] === 'image') {
                    $rule = ['required', Rule::in(Media::pluck('path')->all())];
                }
                $rules[$field['key']] = $rule;
                $attributes[$field['key']] = $field['label'];
            }
        }
        if (in_array($doc->kind, ['page', 'language'])) {
            $rules['seo_title'] = ['required', 'string', 'max:180'];
            $rules['seo_description'] = ['required', 'string', 'max:500'];
        }

        return Validator::make($values, $rules, [], $attributes + ['seo_title' => 'Название в поиске', 'seo_description' => 'Описание в поиске'])->validate();
    }

    public function save(ContentDocument $doc, array $values, int $expectedVersion): ContentDocument
    {
        return DB::transaction(function () use ($doc, $values, $expectedVersion) {
            $fresh = ContentDocument::lockForUpdate()->findOrFail($doc->id);
            if ($fresh->version !== $expectedVersion) {
                throw ValidationException::withMessages(['data.draft' => 'Эту страницу уже изменили в другом окне. Обновите страницу и повторите правку. Ваш текст можно скопировать перед обновлением.']);
            }
            $fresh->update(['draft' => $this->validate($fresh, $values), 'version' => $fresh->version + 1]);

            return $fresh;
        });
    }
}
