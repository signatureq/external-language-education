<?php

namespace App\Services;

use App\Mail\ApplicationReceived;
use App\Models\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ApplicationNotifier
{
    public function send(Application $application): bool
    {
        return (bool) Cache::lock('application-mail:'.$application->id, 60)->get(function () use ($application) {
            $application->refresh();
            if ($application->notified_at) {
                return true;
            }
            $recipient = config('applications.recipient');
            if (! filter_var($recipient, FILTER_VALIDATE_EMAIL) || in_array(config('mail.default'), ['log', 'array']) && app()->environment('production')) {
                return false;
            }
            $application->increment('notification_attempts');
            $application->update(['notification_attempted_at' => now()]);
            try {
                Mail::to($recipient)->send(new ApplicationReceived($application));
                $application->update(['notified_at' => now()]);

                return true;
            } catch (Throwable $error) {
                // Never put contacts, message text, SMTP credentials or mail body into logs.
                Log::warning('Application notification failed', ['reference' => $application->reference, 'error_type' => get_class($error)]);

                return false;
            }
        });
    }
}
