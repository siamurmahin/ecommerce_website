<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Picks bn/en for API copy from `?locale=` or Accept-Language; Bangla by default. */
class SetApiLocale
{
    public const LOCALES = ['bn', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->query('locale') ?? $request->getPreferredLanguage(self::LOCALES);

        app()->setLocale(in_array($locale, self::LOCALES, true) ? $locale : 'bn');

        return $next($request);
    }
}
