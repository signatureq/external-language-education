<?php

namespace App\Services;

use App\Models\ContentDocument;
use Illuminate\Support\Facades\Cache;

class PublishedSiteFeed
{
    public function snapshot(): array
    {
        // Use the same lock as publication so one snapshot cannot mix revisions.
        return Cache::lock('site-publishing', 120)->block(5, fn () => $this->signedSnapshot());
    }

    private function signedSnapshot(): array
    {
        $key = config('publishing.private_key');
        if (! is_file($key)) {
            throw new \RuntimeException('Ключ публикации ещё не установлен.');
        }
        $docs = ContentDocument::orderBy('key')->get();
        // Draft saves never change the data exposed by this public endpoint.
        $version = hash('sha256', json_encode([
            $docs->map(fn ($doc) => [$doc->key, $doc->template, $doc->published])->all(),
            config('publishing.site_url'), config('publishing.media_url'), hash_file('sha256', $key),
        ], JSON_THROW_ON_ERROR));

        return Cache::remember('published-site-feed-v1-'.$version, 30, function () use ($docs, $key) {
            $pages = [];
            foreach ($docs->whereIn('kind', ['page', 'language']) as $page) {
                $pages[$page->key] = app(SitePublisher::class)->render($page);
            }
            $payload = json_encode([
                'schema' => 1,
                'site' => parse_url(config('publishing.site_url'), PHP_URL_HOST),
                'issued_at' => time(),
                'pages' => $pages,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (! openssl_sign($payload, $signature, file_get_contents($key), OPENSSL_ALGO_SHA256)) {
                throw new \RuntimeException('Не удалось подписать публикацию.');
            }

            return ['payload' => base64_encode($payload), 'signature' => base64_encode($signature)];
        });
    }
}
