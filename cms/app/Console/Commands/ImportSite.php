<?php

namespace App\Console\Commands;

use App\Services\SiteImporter;
use Illuminate\Console\Command;

class ImportSite extends Command
{
    protected $signature = 'site:import {--source= : Каталог готовых HTML-страниц}';

    protected $description = 'Однократный импорт существующего сайта без изменения публичных страниц';

    public function handle(SiteImporter $importer): int
    {
        $importer->import($this->option('source') ?: dirname(base_path()));
        $this->info('Страницы, общие блоки и фотографии импортированы.');

        return self::SUCCESS;
    }
}
