<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Services\ApplicationNotifier;
use Illuminate\Console\Command;

class SendApplicationNotifications extends Command
{
    protected $signature = 'applications:notify';

    protected $description = 'Повторить отправку сохранённых заявок, если почта была временно недоступна';

    public function handle(ApplicationNotifier $notifier): int
    {
        if (! config('applications.enabled')) {
            return self::SUCCESS;
        }
        $sent = 0;
        Application::whereNull('notified_at')->where('notification_attempts', '<', 5)
            ->where(fn ($q) => $q->whereNull('notification_attempted_at')->orWhere('notification_attempted_at', '<=', now()->subMinutes(15)))
            ->orderBy('id')->limit(20)->get()->each(function ($application) use ($notifier, &$sent) {
                $sent += (int) $notifier->send($application);
            });
        $this->info('Уведомлений отправлено: '.$sent);

        return self::SUCCESS;
    }
}
