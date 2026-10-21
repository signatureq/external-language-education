<?php

use Illuminate\Contracts\Console\Kernel;

// CLI-only deployment helper. Never put this file in public_html.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$recipient = $argv[1] ?? '';
if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    throw new RuntimeException('Pass the fixed recipient email as the first argument.');
}
$base = __DIR__;
$envFile = $base.'/.env';
if (! is_file($envFile) || ! is_file($base.'/artisan')) {
    throw new RuntimeException('Copy this script to the private Laravel root first.');
}
$backup = $base.'/storage/app/private/applications-install-'.gmdate('Ymd-His');
mkdir($backup, 0700, true);
copy($envFile, $backup.'/.env');
chmod($backup.'/.env', 0600);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$database = config('database.connections.sqlite.database');
$pdo = new PDO('sqlite:'.$database);
$pdo->exec('VACUUM INTO '.$pdo->quote($backup.'/database.sqlite'));
chmod($backup.'/database.sqlite', 0600);
$sender = $argv[2] ?? get_current_user().'@'.gethostname();
if (! filter_var($sender, FILTER_VALIDATE_EMAIL) || ! str_ends_with($sender, '.timeweb.ru')) {
    throw new RuntimeException('Check the Timeweb server sender address first.');
}
$values = [
    'APPLICATIONS_ENABLED' => 'true',
    'APPLICATIONS_RECIPIENT' => $recipient,
    'APPLICATIONS_ENDPOINT' => rtrim(config('app.url'), '/').'/api/applications',
    'APPLICATIONS_ORIGINS' => 'https://externlangschool.ru',
    'MAIL_MAILER' => 'sendmail',
    'MAIL_SENDMAIL_PATH' => '/usr/sbin/sendmail -t -i',
    'MAIL_FROM_ADDRESS' => $sender,
    'MAIL_FROM_NAME' => 'External Language Education',
];
$env = file_get_contents($envFile);
foreach ($values as $key => $value) {
    $line = $key.'="'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    $env = preg_match('/^'.preg_quote($key, '/').'=/m', $env) ? preg_replace_callback('/^'.preg_quote($key, '/').'=.*$/m', fn () => $line, $env) : rtrim($env)."\n".$line."\n";
}
file_put_contents($envFile.'.applications-tmp', $env, LOCK_EX);
chmod($envFile.'.applications-tmp', 0600);
rename($envFile.'.applications-tmp', $envFile);
foreach ([['migrate', ['--force' => true]], ['config:clear', []], ['view:clear', []]] as [$command, $arguments]) {
    $code = $kernel->call($command, $arguments);
    echo $kernel->output();
    if ($code !== 0) {
        throw new RuntimeException($command.' failed');
    }
}
echo 'Applications configured. Backup: '.$backup.PHP_EOL;
