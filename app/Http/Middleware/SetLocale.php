<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

class SetLocale
{
    public function handle($request, Closure $next)
    {
        $langHeader = $request->header('Accept-Language', config('app.locale'));


        // 處理前端傳 Invalid \"zh_TW,zh;q=0.9,en_US;q=0.8,en;q=0.7\" locale.
        $locale = explode(',', $langHeader)[0];
        $allowedLocales = ['zh_TW', 'en'];

        if (!in_array($locale, $allowedLocales)) {
            $locale = config('app.locale');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
