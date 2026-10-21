<?php

namespace App\Console\Commands;

use App\Models\Application;
use Illuminate\Console\Command;

class PruneApplications extends Command
{
    protected $signature = 'applications:prune';

    protected $description = 'Удалить из базы обращения старше срока хранения, указанного в политике сайта';

    public function handle(): int
    {
        $count = Application::where('created_at', '<', now()->subDays(180))->delete();
        $this->info('Удалено устаревших обращений: '.$count);

        return self::SUCCESS;
    }
}
