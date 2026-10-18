<?php

use Illuminate\Support\Facades\Route;

// Local-only responsive inspection harness, never registered in production.
Route::get('/__review', function () {
    abort_unless(auth()->user()?->is_admin, 403);
    $width = (int) request('width', 1440);
    abort_unless(in_array($width, [390, 726, 1440]), 400);
    $page = request('page', '/admin/pages');
    abort_unless(preg_match('~^/admin/(pages|photos|languages|settings|shareds)(/\d+/edit)?$~', $page), 400);

    return '<!doctype html><html><head><title>Проверка интерфейса '.$width.' px</title><style>body{margin:0;background:#303034;width:'.($width + 32).'px;padding:16px;box-sizing:border-box}iframe{border:0;display:block;width:'.$width.'px;height:1050px;background:#09090b}</style></head><body><iframe title="Панель управления" src="'.e($page).'"></iframe></body></html>';
})->middleware('auth');
