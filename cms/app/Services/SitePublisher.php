<?php

namespace App\Services;

use App\Models\ContentDocument;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

class SitePublisher
{
    public function render(ContentDocument $page, ?ContentDocument $draftDocument = null): string
    {
        $docs = ContentDocument::all()->keyBy('key');
        $values = fn (ContentDocument $d) => $draftDocument?->id === $d->id ? $draftDocument->draft : $d->published;
        $html = $this->fill($page->template, $values($page));
        foreach (['header', 'footer', 'contact'] as $shared) {
            if (! $doc = $docs->get('shared.'.$shared)) {
                continue;
            }
            $block = $this->fill($doc->template, $values($doc));
            if ($shared === 'header') {
                $block = str_replace(' aria-current="page"', '', $block);
                $block = str_replace('href="'.$page->key.'"', 'href="'.$page->key.'" aria-current="page"', $block);
            }
            if (in_array($page->key, ['privacy.html', 'consent.html'])) {
                $block = str_replace('href="#application"', 'href="./#application"', $block);
            }
            if ($shared === 'contact' && $page->kind === 'language') {
                $block = str_replace(' selected', '', $block);
                $block = str_replace('<option>'.e($page->name).'</option>', '<option selected>'.e($page->name).'</option>', $block);
            }
            $html = str_replace('<!--CMS_SHARED:'.$shared.'-->', $block, $html);
        }
        $settings = $values($docs->get('settings'));
        $price = (int) $settings['rate'];
        $amounts = [];
        foreach ([900 => 1, 3600 => 4, 14400 => 16, 28800 => 32, 57600 => 64] as $original => $hours) {
            $amounts[number_format($original, 0, '', ' ').' ₽'] = number_format($price * $hours, 0, '', ' ').' ₽';
            $amounts[(string) $original.' ₽'] = number_format($price * $hours, 0, '', ' ').' ₽';
        }
        $amounts['9:00–13:30'] = $settings['morning'];
        $amounts['17:30–22:00'] = $settings['evening'];
        $html = strtr($html, $amounts);
        $data = $values($page);
        $data['seo_title'] = strtr($data['seo_title'], $amounts);
        $data['seo_description'] = strtr($data['seo_description'], $amounts);
        $title = htmlspecialchars($data['seo_title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $description = htmlspecialchars($data['seo_description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = preg_replace_callback('~<title>.*?</title>~s', fn () => '<title>'.$title.'</title>', $html);
        $html = preg_replace_callback('~<meta\b[^>]+>~i', function ($m) use ($title, $description) {
            if (preg_match('~(?:name|property)="(?:description|og:description|twitter:description)"~', $m[0])) {
                return preg_replace_callback('~content="[^"]*"~', fn () => 'content="'.$description.'"', $m[0]);
            }
            if (preg_match('~(?:name|property)="(?:og:title|twitter:title)"~', $m[0])) {
                return preg_replace_callback('~content="[^"]*"~', fn () => 'content="'.$title.'"', $m[0]);
            }

            return $m[0];
        }, $html);
        $html = preg_replace_callback('~<script type="application/ld\+json">(.*?)</script>~s', function ($m) use ($data) {
            $graph = json_decode($m[1], true);
            if (! $graph) {
                return $m[0];
            }
            $patch = function (&$item) use (&$patch, $data) {
                if (! is_array($item)) {
                    return;
                } if (($item['@type'] ?? '') === 'WebPage') {
                    $item['name'] = $data['seo_title'];
                    $item['description'] = $data['seo_description'];
                }foreach ($item as &$child) {
                    if (is_array($child)) {
                        $patch($child);
                    }
                }
            };
            $patch($graph);

            return '<script type="application/ld+json">'.json_encode($graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP).'</script>';
        }, $html);
        preg_match('~<img[^>]+data-schedule-image[^>]+src="([^"]+)"~', $html, $scheduleImage);
        $config = json_encode(['morningPhoto' => $scheduleImage[1] ?? 'assets/photos/blue/group-practice.jpg', 'rate' => $price, 'morning' => $settings['morning'], 'evening' => $settings['evening']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
        if (is_file(public_path('app.js'))) {
            $html = preg_replace('~src="app\.js(?:\?[^"]*)?"~', 'src="app.js?v='.substr(hash_file('sha256', public_path('app.js')), 0, 10).'"', $html);
        }

        $html = str_replace('</head>', '<script>window.schoolSettings='.$config.';</script></head>', $html);
        if ($mediaUrl = config('publishing.media_url')) {
            $html = preg_replace_callback('~(?<![a-zA-Z0-9/:])uploads/images/[a-f0-9]{40}\.webp~', fn ($m) => rtrim($mediaUrl, '/').'/'.$m[0], $html);
        }

        return $html;
    }

    private function fill(string $template, array $data): string
    {
        $replacements = [];
        foreach ($data as $key => $value) {
            $replacements['CMSVALUE_'.$key.'_END'] = htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        $html = strtr($template, $replacements);
        if (preg_match('/CMSVALUE_\w+_END/', $html)) {
            throw new \RuntimeException('В шаблоне не найдено значение поля. Публикация отменена.');
        }

        return $html;
    }

    public function publish(ContentDocument $document, int $expectedVersion, string $author): void
    {
        Cache::lock('site-publishing', 120)->block(5, function () use ($document, $expectedVersion, $author) {
            $backup = storage_path('app/private/publications/'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(3)));
            File::ensureDirectoryExists($backup);
            $written = [];
            try {
                DB::transaction(function () use ($document, $expectedVersion, $author, $backup, &$written) {
                    $doc = ContentDocument::lockForUpdate()->findOrFail($document->id);
                    if ($doc->version !== $expectedVersion) {
                        throw ValidationException::withMessages(['data.draft' => 'Страница уже изменилась. Обновите её перед публикацией.']);
                    }
                    app(ContentEditor::class)->validate($doc, $doc->draft);
                    $pages = in_array($doc->kind, ['shared', 'settings']) ? ContentDocument::whereIn('kind', ['page', 'language'])->get() : collect([$doc]);
                    // Stage all output first; no public file changes if rendering fails.
                    $staged = [];
                    foreach ($pages as $page) {
                        if (! preg_match('/^[a-z]+\.html$/', $page->key)) {
                            throw new \RuntimeException('Неверный адрес страницы');
                        }
                        $staged[$page->key] = $this->render($page, $doc);
                    }
                    foreach (config('publishing.separate_domain') ? [] : $staged as $file => $html) {
                        $target = public_path($file);
                        if (is_file($target)) {
                            File::copy($target, $backup.'/'.$file);
                        }
                        File::put($target.'.cms-tmp', $html);
                        chmod($target.'.cms-tmp', 0644);
                        if (! rename($target.'.cms-tmp', $target)) {
                            throw new \RuntimeException('Не удалось сохранить страницу');
                        }
                        $written[] = $file;
                    }
                    $doc->revisions()->create(['data' => $doc->published, 'author' => $author]);
                    $doc->update(['published' => $doc->draft, 'published_at' => now(), 'version' => $doc->version + 1]);
                });
            } catch (\Throwable $e) {
                foreach ($written as $file) {
                    if (is_file($backup.'/'.$file)) {
                        File::copy($backup.'/'.$file, public_path($file));
                    } else {
                        @unlink(public_path($file));
                    }
                }
                throw $e;
            } finally {
                foreach (glob(public_path('*.cms-tmp')) as $temp) {
                    @unlink($temp);
                }
            }
        });
    }
}
