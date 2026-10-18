<?php
// Run from a private account directory with PHP CLI. Never expose this file on the web.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$base=__DIR__;
$public=dirname($base).'/public_html';
foreach(['pdo_sqlite','mbstring','intl','fileinfo','dom','openssl','gd'] as $extension)if(!extension_loaded($extension))throw new RuntimeException('Missing PHP extension: '.$extension);
if(version_compare(PHP_VERSION,'8.2.0','<'))throw new RuntimeException('PHP 8.2 or later is required.');
if(file_exists($base.'/.env'))throw new RuntimeException('Already configured. Existing content and access have been preserved.');
if(!is_file($public.'/index.html'))throw new RuntimeException('Existing public website not found.');
$db=$base.'/database/database.sqlite';touch($db);chmod($db,0600);
$env=file_get_contents($base.'/.env.example');
$env=str_replace(['APP_ENV=local','APP_URL=http://127.0.0.1:8008'],['APP_ENV=production','APP_URL=https://externlangschool.ru'],$env);
$env.="\nDB_DATABASE=\"".$db."\"\nCMS_PUBLIC_PATH=\"".$public."\"\nSESSION_SECURE_COOKIE=true\n";
file_put_contents($base.'/.env',$env);chmod($base.'/.env',0600);
require $base.'/vendor/autoload.php';
$app=require $base.'/bootstrap/app.php';$kernel=$app->make(Illuminate\Contracts\Console\Kernel::class);$kernel->bootstrap();
foreach([['key:generate',['--force'=>true]],['migrate',['--force'=>true]],['site:import',['--source'=>$public]],['filament:assets',[]],['site:build',[]],['site:setup-link',[]]] as [$command,$args]){
    $exit=$kernel->call($command,$args);echo $kernel->output();if($exit!==0)throw new RuntimeException($command.' failed');
}
