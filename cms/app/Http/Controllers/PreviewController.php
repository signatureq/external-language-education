<?php

namespace App\Http\Controllers;

use App\Filament\Resources\ContentResource;
use App\Models\ContentDocument;
use App\Services\SitePublisher;

class PreviewController
{
    public function __invoke(ContentDocument $document, SitePublisher $publisher)
    {
        abort_unless(auth()->user()?->is_admin, 403);
        $page = in_array($document->kind, ['shared', 'settings']) ? ContentDocument::where('key', request('page', 'index.html'))->whereIn('kind', ['page', 'language'])->firstOrFail() : $document;
        $html = $publisher->render($page, $document);
        $banner = '<aside style="position:fixed;bottom:16px;left:16px;right:16px;z-index:99999;display:flex;justify-content:space-between;align-items:center;gap:12px;background:#171717;color:#fff;border:1px solid #525252;border-radius:12px;padding:14px 20px;font:14px/1.5 system-ui"><span>Предпросмотр сохранённого черновика — посетители его не видят</span><a style="color:#ffad72;white-space:nowrap" href="'.e(ContentResource::editorUrl($document)).'">Вернуться к редактору</a></aside>';
        $html = preg_replace('~<meta name="robots"[^>]*>~', '', $html);
        $html = str_replace('<head>', '<head><base href="'.e(url('/')).'/"><meta name="robots" content="noindex,nofollow">', $html);
        $html = str_replace('</body>', $banner.'</body>', $html);

        return response($html)->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
