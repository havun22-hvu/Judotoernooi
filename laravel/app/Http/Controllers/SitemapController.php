<?php

namespace App\Http\Controllers;

use App\Models\Toernooi;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $appUrl = rtrim(config('app.url'), '/');
        $locales = config('app.available_locales', ['nl', 'en']);

        // Static pages
        $staticPages = [
            ['url' => '/', 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['url' => '/help', 'priority' => '0.5', 'changefreq' => 'monthly'],
            ['url' => '/algemene-voorwaarden', 'priority' => '0.3', 'changefreq' => 'yearly'],
            ['url' => '/privacyverklaring', 'priority' => '0.3', 'changefreq' => 'yearly'],
            ['url' => '/cookiebeleid', 'priority' => '0.3', 'changefreq' => 'yearly'],
            ['url' => '/disclaimer', 'priority' => '0.3', 'changefreq' => 'yearly'],
        ];

        // Active public tournaments (not closed, with a date in the future or recent past)
        $toernooien = Toernooi::with('organisator')
            ->where('datum', '>=', now()->subMonths(3))
            ->whereNull('afgesloten_op')
            ->whereHas('organisator')
            ->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        $xml .= ' xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        // Static pages with hreflang
        foreach ($staticPages as $page) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . $appUrl . $page['url'] . '</loc>' . "\n";
            foreach ($locales as $locale) {
                $xml .= '    <xhtml:link rel="alternate" hreflang="' . $locale . '" href="' . $appUrl . $page['url'] . '?locale=' . $locale . '" />' . "\n";
            }
            $xml .= '    <changefreq>' . $page['changefreq'] . '</changefreq>' . "\n";
            $xml .= '    <priority>' . $page['priority'] . '</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }

        // Tournament pages
        foreach ($toernooien as $toernooi) {
            if (!$toernooi->organisator) {
                continue;
            }

            $toernooiUrl = $appUrl . '/' . $toernooi->organisator->slug . '/' . $toernooi->slug;
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . $toernooiUrl . '</loc>' . "\n";
            foreach ($locales as $locale) {
                $xml .= '    <xhtml:link rel="alternate" hreflang="' . $locale . '" href="' . $toernooiUrl . '?locale=' . $locale . '" />' . "\n";
            }
            $xml .= '    <changefreq>daily</changefreq>' . "\n";
            $xml .= '    <priority>0.8</priority>' . "\n";
            if ($toernooi->updated_at) {
                $xml .= '    <lastmod>' . $toernooi->updated_at->toW3cString() . '</lastmod>' . "\n";
            }
            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }
}
