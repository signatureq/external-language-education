<?php

namespace App\Http\Controllers;

use App\Services\PublishedSiteFeed;

class PublishedSiteController
{
    public function __invoke(PublishedSiteFeed $feed)
    {
        try {
            $snapshot = $feed->snapshot();
        } catch (\Throwable $error) {
            report($error);
            abort(503);
        }

        return response()->json($snapshot)
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
