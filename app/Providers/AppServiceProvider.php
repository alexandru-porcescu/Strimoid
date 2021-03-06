<?php

namespace Strimoid\Providers;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Pdp\Cache;
use Pdp\CurlHttpClient;
use Pdp\Manager;
use Strimoid\Helpers\OEmbed;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->environment('local')) {
            $this->app->register('Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider');
            $this->app->register('Barryvdh\Debugbar\ServiceProvider');
        }

        $dsn = config('services.raven.dsn');

        if (!empty($dsn)) {
            $this->app->register(\Jenssegers\Raven\RavenServiceProvider::class);
        }

        $locale = $this->detectLocale();
        \App::setLocale($locale);
        Carbon::setLocale($locale);

        Paginator::$defaultView = 'pagination::bootstrap-4';
        Paginator::$defaultSimpleView = 'pagination::simple-bootstrap-4';

        \Request::setTrustedProxies(
            ['10.0.0.0/8', '172.16.0.0/12', 'fd00::/8'],
            \Illuminate\Http\Request::HEADER_X_FORWARDED_ALL
        );
    }

    public function register(): void
    {
        $this->app->bind('guzzle', fn () => new Client([
            'connect_timeout' => 3,
            'timeout' => 10,
        ]));

        $this->app->bind('pdp', fn () => (new Manager(
            new Cache(),
            new CurlHttpClient()
        ))->getRules());

        $this->app->bind('oembed', fn () => new OEmbed());
    }

    private function detectLocale()
    {
        $userLocales = \Agent::languages();

        foreach (['pl', 'en'] as $locale) {
            if (in_array($locale, $userLocales)) {
                return $locale;
            }
        }

        return config('app.locale');
    }
}
