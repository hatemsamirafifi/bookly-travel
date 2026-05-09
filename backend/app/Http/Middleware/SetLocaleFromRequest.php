<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocaleFromRequest
{
    protected array $supportedLocales = ['en', 'es', 'it'];

    public function handle(Request $request, Closure $next)
    {
        $locale = $request->query('locale')
            ?? $request->header('Accept-Language')
            ?? 'en';

        // Extract first 2 chars from Accept-Language header (e.g. "es-ES,es;q=0.9" → "es")
        if (strlen($locale) > 2) {
            $locale = substr($locale, 0, 2);
        }

        if (! in_array($locale, $this->supportedLocales)) {
            $locale = 'en';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
