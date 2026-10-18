<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateAdministrator extends Command
{
    protected $signature = 'site:administrator';

    protected $description = 'Создать администратора сайта (пароль вводится скрыто)';

    public function handle(): int
    {
        $name = $this->ask('Как к вам обращаться?');
        $email = $this->ask('Почта для входа');
        $password = $this->secret('Пароль (не менее 12 символов)');
        validator(compact('name', 'email', 'password'), ['name' => 'required|string|max:100', 'email' => 'required|email|unique:users,email', 'password' => 'required|string|min:12'])->validate();
        $user = new User(compact('name', 'email', 'password'));
        $user->is_admin = true;
        $user->save();
        $this->info('Администратор создан. Вход: /admin');

        return self::SUCCESS;
    }
}
