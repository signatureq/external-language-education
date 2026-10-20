<?php

use External\Publishing\PublishedSiteGateway;

require __DIR__.'/cms-publishing/PublishedSiteGateway.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = $path === '/' ? 'index.html' : ltrim((string) $path, '/');
if (! in_array($file, PublishedSiteGateway::PAGES, true)) {
    http_response_code(404);
    exit;
}
if (! in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit;
}
$source = 'fallback';
try {
    $config = require __DIR__.'/cms-publishing/config.php';
    $gateway = new PublishedSiteGateway(__DIR__, $config['source'], $config['public_key'], $config['site']);
    $html = $gateway->page($file);
    $source = $gateway->servedFromCms() ? 'signed' : 'fallback';
} catch (Throwable $error) {
    error_log('External site gateway: '.$error->getMessage());
    $fallback = __DIR__.'/cms-publishing/fallback/'.$file;
    $html = @file_get_contents(is_file($fallback) ? $fallback : __DIR__.'/'.$file);
}
if ($html === false || $html === '') {
    http_response_code(503);
    exit('Страница временно недоступна');
}
$etag = '"'.hash('sha256', $html).'"';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=0, must-revalidate');
header('ETag: '.$etag);
header('X-External-Publication: '.substr(hash('sha256', $html), 0, 12));
header('X-External-Source: '.$source);
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
    echo $html;
}
