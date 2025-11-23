<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

class SetLocale
{
    public function handle($request, Closure $next)
    {
        $locale = $request->header('Accept-Language', config('app.locale'));

        app()->setLocale($locale);

        return $next($request);
    }
}
