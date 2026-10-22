<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Mail;

// Copy to the private Laravel root. Never publish this helper or the password file.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$base = __DIR__;
$envFile = $base.'/.env';
$passwordFile = realpath($argv[1] ?? '');
$privateDirectory = realpath($base.'/storage/app/private');
if (! is_file($envFile) || ! is_file($base.'/artisan') || ! $passwordFile || ! $privateDirectory
    || ! str_starts_with($passwordFile, $privateDirectory.DIRECTORY_SEPARATOR)) {
    fwrite(STDERR, "Run from the private Laravel root with a password file in storage/app/private.\n");
    exit(1);
}

$stage = 'read-password';
$original = null;
$changed = false;
$kernel = null;
try {
    $password = rtrim(file_get_contents($passwordFile), "\r\n");
    if ($password === '' || strlen($password) > 512 || strpbrk($password, "\r\n\0") !== false) {
        throw new RuntimeException('Invalid password file.');
    }
    require $base.'/vendor/autoload.php';
    $app = require $base.'/bootstrap/app.php';
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();

    $sender = 'notifications@externlangschool.ru';
    $smtp = [
        'transport' => 'smtp',
        'scheme' => 'smtps',
        'host' => 'smtp.timeweb.ru',
        'port' => 465,
        'username' => $sender,
        'password' => $password,
        'timeout' => 15,
        'local_domain' => 'externlangschool.ru',
    ];

    // Verify TLS and authentication before changing the working mail configuration.
    // Symfony's default certificate verification remains enabled.
    $stage = 'smtp-authentication';
    $transport = Mail::build($smtp)->getSymfonyTransport();
    $transport->start();
    $transport->stop();

    $stage = 'prepare-env';
    $original = file_get_contents($envFile);
    $env = $original;
    $values = [
        'MAIL_MAILER' => 'smtp',
        'MAIL_SCHEME' => 'smtps',
        'MAIL_URL' => null,
        'MAIL_HOST' => 'smtp.timeweb.ru',
        'MAIL_PORT' => '465',
        'MAIL_USERNAME' => $sender,
        'MAIL_PASSWORD' => $password,
        'MAIL_EHLO_DOMAIN' => 'externlangschool.ru',
        'MAIL_TIMEOUT' => '15',
        'MAIL_FROM_ADDRESS' => $sender,
        'MAIL_FROM_NAME' => 'External Language Education',
    ];
    foreach ($values as $key => $value) {
        $quoted = $value === null ? 'null' : '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
        $line = $key.'='.$quoted;
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
        $env = preg_match($pattern, $env)
            ? preg_replace_callback($pattern, fn () => $line, $env)
            : rtrim($env)."\n".$line."\n";
    }

    // Catch quoting/interpolation errors before writing a credential to .env.
    $parsed = Dotenv\Dotenv::parse($env);
    if (! hash_equals($password, $parsed['MAIL_PASSWORD'] ?? '')
        || ($parsed['APPLICATIONS_RECIPIENT'] ?? null) !== (Dotenv\Dotenv::parse($original)['APPLICATIONS_RECIPIENT'] ?? null)) {
        throw new RuntimeException('Environment validation failed.');
    }

    $stage = 'save-configuration';
    $backup = $privateDirectory.'/smtp-install-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3));
    if (! mkdir($backup, 0700) || ! copy($envFile, $backup.'/.env')) {
        throw new RuntimeException('Could not back up environment.');
    }
    chmod($backup.'/.env', 0600);
    $temporary = $envFile.'.smtp-tmp';
    file_put_contents($temporary, $env, LOCK_EX);
    chmod($temporary, 0600);
    if (! rename($temporary, $envFile)) {
        throw new RuntimeException('Could not install environment.');
    }
    $changed = true;
    if ($kernel->call('config:clear') !== 0) {
        throw new RuntimeException('Could not clear configuration cache.');
    }

    unlink($passwordFile);
    echo "SMTP_CONFIGURED\n";
    echo "Sender: ".$sender."\n";
    echo "Server: smtp.timeweb.ru:465 (TLS)\n";
    echo "Applications recipient unchanged. No email sent by this helper.\n";
    echo "Private backup: ".$backup."\n";
} catch (Throwable $error) {
    if ($changed && $original !== null) {
        file_put_contents($envFile, $original, LOCK_EX);
        chmod($envFile, 0600);
        $kernel?->call('config:clear');
    }
    // Do not print exception text: SMTP errors can contain authentication data.
    fwrite(STDERR, 'SMTP_SETUP_FAILED stage='.$stage.' type='.get_class($error)."\n");
    exit(1);
}
