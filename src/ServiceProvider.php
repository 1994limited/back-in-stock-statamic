<?php

namespace NineteenNinetyFour\BackInStock;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use NineteenNinetyFour\BackInStock\Cargo\Stock;
use NineteenNinetyFour\BackInStock\Commands\Check;
use NineteenNinetyFour\BackInStock\Commands\Forget;
use NineteenNinetyFour\BackInStock\Commands\Prune;
use NineteenNinetyFour\BackInStock\Listeners\StockSubscriber;
use NineteenNinetyFour\BackInStock\Tags\BackInStockTags;
use NineteenNinetyFour\BackInStock\Widgets\BackInStockWidget;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Facades\YAML;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Statamic;

class ServiceProvider extends AddonServiceProvider
{
    /** The Control Panel nav icon (resources/brand/nav-icon.svg). */
    public const ICON = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14"><path d="M3.5 6A3.5 3.5 0 0 1 10.5 6V8.5L11.5 10.5H2.5L3.5 8.5Z" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"></path><path d="M5.75 12.25A1.4 1.4 0 0 0 8.25 12.25" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"></path><path d="M5.6 5.9L6.6 6.9L8.4 5" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"></path></svg>';

    protected $tags = [
        BackInStockTags::class,
    ];

    protected $commands = [
        Check::class,
        Prune::class,
        Forget::class,
    ];

    protected $widgets = [
        BackInStockWidget::class,
    ];

    protected $subscribe = [
        StockSubscriber::class,
    ];

    protected $vite = [
        'input' => [
            'resources/js/cp.js',
            'resources/css/cp.css',
        ],
        'publicDirectory' => 'resources/dist',
        'hotFile' => __DIR__.'/../resources/dist/hot',
    ];

    /**
     * The package is 1994/back-in-stock-statamic; its config, views and
     * translations are read as "back-in-stock", so they're loaded by hand.
     * Routes too: actions live at /!/back-in-stock.
     */
    protected $config = false;

    protected $translations = false;

    protected $routes = [
        'web' => null,
        'cp' => __DIR__.'/../routes/cp.php',
        'actions' => null,
    ];

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__.'/../config/back-in-stock.php', 'back-in-stock');

        $this->app->singleton(Stock::class);
        $this->app->singleton(Watcher::class);
        $this->app->singleton(Sender::class);
        $this->app->singleton(BackInStock::class);
    }

    public function bootAddon(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'back-in-stock');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'back-in-stock');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([__DIR__.'/../config/back-in-stock.php' => config_path('back-in-stock.php')], 'back-in-stock-config');
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/back-in-stock')], 'back-in-stock-views');
        $this->publishes([__DIR__.'/../lang' => $this->app->langPath('vendor/back-in-stock')], 'back-in-stock-translations');
        $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'back-in-stock-migrations');

        $this->registerFrontendRoutes();
        $this->registerSchedule();
        $this->registerSettings();

        Permission::extend(function () {
            Permission::group('back-in-stock', __('back-in-stock::messages.permissions.group'), function () {
                Permission::register('view back in stock', function ($permission) {
                    $permission->label(__('back-in-stock::messages.permissions.view'))->children([
                        Permission::make('manage back in stock')->label(__('back-in-stock::messages.permissions.manage')),
                        Permission::make('export back in stock')->label(__('back-in-stock::messages.permissions.export')),
                    ]);
                });
            });
        });

        Nav::extend(function ($nav) {
            $nav->create(__('back-in-stock::messages.cp.title'))
                ->section('Store')
                ->route('back-in-stock.index')
                ->icon(self::ICON)
                ->can('view back in stock');
        });
    }

    /**
     * Tools → Addons → Back in Stock → Settings (resources/settings.yaml),
     * with "Who to email" defaulting to the config, so a setting nobody
     * has touched doesn't override config/back-in-stock.php.
     */
    protected function registerSettings(): void
    {
        $this->registerSettingsBlueprint(function () {
            $blueprint = YAML::file(__DIR__.'/../resources/settings.yaml')->parse();

            foreach ($blueprint['tabs']['sending']['sections'][0]['fields'] as &$field) {
                if ($field['handle'] === 'limit') {
                    $field['field']['default'] = config('back-in-stock.limit') === 'stock' ? 'stock' : 'all';
                }
            }

            // The logo needs an asset container: the first one, if there
            // are several. Without one, there's no logo field.
            $container = AssetContainer::all()->first();

            foreach ($blueprint['tabs']['emails']['sections'][0]['fields'] as $i => &$field) {
                if ($field['handle'] === 'logo') {
                    if ($container) {
                        $field['field']['container'] = $container->handle();
                    } else {
                        unset($blueprint['tabs']['emails']['sections'][0]['fields'][$i]);
                    }
                }
            }

            $blueprint['tabs']['emails']['sections'][0]['fields'] = array_values($blueprint['tabs']['emails']['sections'][0]['fields']);

            return $blueprint;
        });
    }

    protected function registerFrontendRoutes(): void
    {
        RateLimiter::for('back-in-stock', fn (Request $request) => Limit::perMinute(max(1, (int) config('back-in-stock.throttle.subscribe', 10)))->by($request->ip()));

        // The links in the emails post without a CSRF token (mail apps'
        // one-click unsubscribe can't send one); their signature is checked
        // instead. VerifyCsrfToken's list is shared by Laravel 12's
        // ValidateCsrfToken and Laravel 13's PreventRequestForgery, and by
        // a site's own subclass.
        $actions = trim((string) config('statamic.routes.action', '!'), '/');
        VerifyCsrfToken::except([
            $actions.'/back-in-stock/confirm/*',
            $actions.'/back-in-stock/unsubscribe/*',
        ]);

        Statamic::pushActionRoutes(function () {
            Route::prefix('back-in-stock')->name('back-in-stock.')->group(__DIR__.'/../routes/actions.php');
        });
    }

    protected function registerSchedule(): void
    {
        if (! config('back-in-stock.schedule', true)) {
            return;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('back-in-stock:check')->everyFiveMinutes()->withoutOverlapping();
            $schedule->command('back-in-stock:prune')->daily()->withoutOverlapping();
        });
    }
}
