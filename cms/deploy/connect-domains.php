<?php

use External\Publishing\PublishedSiteGateway;

// One-time, CLI-only layout migration. No files or credentials are deleted.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$cms = __DIR__;
$main = $argv[1] ?? '';
$admin = dirname($cms).'/public_html';
if (! is_dir($main) || ! is_file($main.'/cms-publishing/config.php') || ! is_file($cms.'/.env')) {
    throw new RuntimeException('Expected an installed CMS and prepared main-site gateway.');
}
require $main.'/cms-publishing/PublishedSiteGateway.php';
$config = require $main.'/cms-publishing/config.php';
if (! extension_loaded('curl') || ! extension_loaded('openssl')) {
    throw new RuntimeException('PHP cURL and OpenSSL are required.');
}
$curl = curl_init($config['source']);
curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
$body = curl_exec($curl);
$status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
curl_close($curl);
if ($status !== 200 || ! is_string($body) || strlen($body) > 16777216) {
    throw new RuntimeException('The publication feed must be available before switching the site.');
}
$envelope = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
$payload = base64_decode($envelope['payload'], true);
if (openssl_verify($payload, base64_decode($envelope['signature'], true), $config['public_key'], OPENSSL_ALGO_SHA256) !== 1) {
    throw new RuntimeException('Publication signature verification failed.');
}
$snapshot = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
$files = array_keys($snapshot['pages'] ?? []);
sort($files);
if (($snapshot['site'] ?? null) !== $config['site'] || $files !== PublishedSiteGateway::PAGES) {
    throw new RuntimeException('The signed publication does not match this website.');
}
$fallback = $main.'/cms-publishing/fallback';
foreach ($files as $file) {
    if (! is_file($main.'/'.$file) && ! is_file($fallback.'/'.$file)) {
        throw new RuntimeException('A static fallback is missing: '.$file);
    }
}
$backup = $cms.'/storage/app/private/domain-connection-'.gmdate('Ymd-His');
mkdir($backup, 0700, true);
mkdir($backup.'/admin-pages', 0700);
if (! is_dir($fallback)) {
    mkdir($fallback, 0700, true);
}
copy($cms.'/.env', $backup.'/cms.env');
chmod($backup.'/cms.env', 0600);
copy($main.'/.htaccess', $backup.'/main.htaccess');
if (is_file($main.'/index.php')) {
    copy($main.'/index.php', $backup.'/main-index.php');
}
// Keep original HTML as a fallback. Nginx serves existing .html files directly,
// so their original URLs must pass through the PHP gateway after this move.
copy($main.'/cms-site.php', $main.'/index.php');
chmod($main.'/index.php', 0644);
$rules = file_get_contents($main.'/.htaccess');
$rules = preg_replace('/^DirectoryIndex[^\r\n]*/m', 'DirectoryIndex index.php', $rules);
file_put_contents($main.'/.htaccess.cms-tmp', $rules);
chmod($main.'/.htaccess.cms-tmp', 0644);
rename($main.'/.htaccess.cms-tmp', $main.'/.htaccess');
foreach (array_merge($files, ['index.htm']) as $file) {
    if (is_file($main.'/'.$file)) {
        if (is_file($fallback.'/'.$file)) {
            rename($main.'/'.$file, $backup.'/main-'.$file);
        } else {
            rename($main.'/'.$file, $fallback.'/'.$file);
        }
    }
    if (is_file($admin.'/'.$file)) {
        rename($admin.'/'.$file, $backup.'/admin-pages/'.$file);
    }
}
$env = file_get_contents($cms.'/.env');
foreach (['APP_URL' => 'https://cw704626.tw1.ru', 'CMS_SITE_URL' => 'https://externlangschool.ru', 'CMS_MEDIA_URL' => 'https://cw704626.tw1.ru', 'CMS_SEPARATE_DOMAIN' => 'true'] as $key => $value) {
    $env = preg_replace('/^'.preg_quote($key, '/').'=.*\R?/m', '', $env);
    $env .= "\n".$key.'='.$value."\n";
}
file_put_contents($cms.'/.env', $env);
chmod($cms.'/.env', 0600);
echo 'Connected '.count($files).' pages. Original files and configuration preserved in '.$backup.PHP_EOL;
