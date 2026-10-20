<?php

namespace App\Console\Commands;

use App\Models\ContentDocument;
use App\Services\SitePublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class BuildPublishedSite extends Command
{
    protected $signature = 'site:build';

    protected $description = 'Пересобрать сайт только из опубликованных версий, не затрагивая черновики';

    public function handle(SitePublisher $publisher): int
    {
        Cache::lock('site-publishing', 120)->block(5, function () use ($publisher) {
            $pages = ContentDocument::whereIn('kind', ['page', 'language'])->get();
            $output = [];
            foreach ($pages as $page) {
                $output[$page->key] = $publisher->render($page);
            }
            foreach (config('publishing.separate_domain') ? [] : $output as $file => $html) {
                File::put(public_path($file.'.cms-tmp'), $html);
                chmod(public_path($file.'.cms-tmp'), 0644);
                rename(public_path($file.'.cms-tmp'), public_path($file));
            }
        });
        $this->info('Опубликованные страницы собраны.');

        return self::SUCCESS;
    }
}
