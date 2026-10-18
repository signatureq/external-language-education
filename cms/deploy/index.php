<?php
use Illuminate\Http\Request;
define('LARAVEL_START',microtime(true));
$cms=dirname(__DIR__).'/ele-cms';
if(file_exists($maintenance=$cms.'/storage/framework/maintenance.php'))require $maintenance;
require $cms.'/vendor/autoload.php';
$app=require_once $cms.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);
$app->handleRequest(Request::capture());
