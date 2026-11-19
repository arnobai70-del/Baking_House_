<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * Dynamic robots.txt: disallow everything until indexing is explicitly enabled for launch.
 */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = ['User-agent: *'];

        if (! config('seo.indexing_enabled')) {
            $lines[] = 'Disallow: /';
        } else {
            foreach (config('seo.disallow') as $path) {
                $lines[] = "Disallow: {$path}";
            }
            $lines[] = 'Disallow: /*?*sort=';
            $lines[] = '';
            $lines[] = 'Sitemap: '.route('sitemap');
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
