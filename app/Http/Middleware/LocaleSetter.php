<?php

namespace App\Http\Middleware;

use App\Providers\LocalesServiceProvider;
use Carbon\Carbon;
use Closure;
use Cookie;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;

class LocaleSetter
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Getting the preferred language
        $code = LocalesServiceProvider::getUserPreferredLocale($request);
        LocalesServiceProvider::setLocale($code);

//        Custom Carbon language overrides sample
//        $carbonTranslations = Carbon::getTranslator();
//        $carbonTranslations->addResource('array', require base_path('resources/lang/ro/carbon.php'), 'ru');
//        $carbonTranslations->setLocale('ro');

        // Prepping the translation files for frontend usage
        $langPath = app()->langPath().'/'.App::getLocale();
        // Avoiding any 500 errors
        if (!file_exists($langPath.'.json')) {
            $langPath = app()->langPath().'/en';
            LocalesServiceProvider::setLocale('en');
        }
        $translations = LocalesServiceProvider::getCachedTranslations(
            App::getLocale(),
            $langPath.'.json'
        );

        session()->forget('app_translations');
        View::share('appTranslations', $translations);

        return $next($request);
    }
}
