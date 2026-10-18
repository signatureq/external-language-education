<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetupLink extends Command
{
    protected $signature = 'site:setup-link';

    protected $description = 'Создать одноразовую ссылку для первого администратора (действует 24 часа)';

    public function handle(): int
    {
        if (User::where('is_admin', true)->exists()) {
            $this->error('Администратор уже создан.');

            return self::FAILURE;
        }
        $token = bin2hex(random_bytes(32));
        $env = file_get_contents(base_path('.env'));
        $env = preg_replace('/^ADMIN_SETUP_(TOKEN_HASH|EXPIRES)=.*\R?/m', '', $env);
        $env .= "\nADMIN_SETUP_TOKEN_HASH=".hash('sha256', $token)."\nADMIN_SETUP_EXPIRES=\"".now()->addDay()->toIso8601String()."\"\n";
        file_put_contents(base_path('.env'), $env);
        $this->callSilent('config:clear');
        $this->line(rtrim(config('app.url'), '/').'/admin/setup/'.$token);

        return self::SUCCESS;
    }
}
