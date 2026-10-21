<?php

use App\Models\ContentDocument;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Run once from the private Laravel root; preserve unrelated content and drafts.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$replacements = json_decode(file_get_contents(__DIR__.'/application-copy.json'), true, flags: JSON_THROW_ON_ERROR);
Cache::lock('site-publishing', 120)->block(5, function () use ($replacements) {
    DB::transaction(function () use ($replacements) {
        foreach (ContentDocument::whereIn('key', ['privacy.html', 'consent.html'])->lockForUpdate()->get() as $doc) {
            $replace = fn (array $values) => array_map(fn ($value) => is_string($value) ? strtr($value, $replacements) : $value, $values);
            $published = $replace($doc->published);
            $draft = $replace($doc->draft);
            if ($published === $doc->published && $draft === $doc->draft) {
                continue;
            }
            $doc->revisions()->create(['data' => $doc->published, 'author' => 'Подключение приёма заявок']);
            $doc->update(['published' => $published, 'draft' => $draft, 'version' => $doc->version + 1, 'published_at' => now()]);
            echo 'Updated: '.$doc->key.PHP_EOL;
        }
    });
});
