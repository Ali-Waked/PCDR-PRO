<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use Illuminate\Http\Response;

final class SitemapController
{
    public function __invoke(): Response
    {
        $locales = (array) config('website.locales', ['ar', 'en']);
        $entries = [];

        foreach ($locales as $locale) {
            $loc = url('/'.$locale);
            $alternates = '';

            foreach ($locales as $alternate) {
                $alternates .= '<xhtml:link rel="alternate" hreflang="'.$alternate.'" href="'.e(url('/'.$alternate)).'"/>';
            }

            $entries[] = '  <url><loc>'.e($loc).'</loc><priority>1.0</priority>'.$alternates.'</url>'."\n";
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";
        $xml .= implode('', $entries);
        $xml .= '</urlset>';

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=utf-8');
    }
}
