<?php

/*
 * This file is part of ibrand/laravel-shopping-cart.
 *
 * (c) iBrand <https://www.ibrand.cc>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace iBrand\Shoppingcart;

use iBrand\Shoppingcart\Storage\SessionStorage;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;

/**
 * Service provider for Laravel.
 */
class ServiceProvider extends LaravelServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Boot the provider.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->registerMigrations();
        }
        //
        //publish a config file
        $this->publishes([
            __DIR__ . '/config.php' => config_path('cart.php'),
            __DIR__ . '/../migrations/' => database_path('/migrations'),
        ]);
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        // merge configs
        $this->mergeConfigFrom(
            __DIR__ . '/config.php', 'cart'
        );

        $this->app->singleton(Cart::class, function ($app) {
            $storage = config('cart.storage');

            $cart = new Cart(new $storage(), $app['events']);

            if (SessionStorage::class == $storage) {
                return $cart;
            }

            //The below code is used of database storage
            $currentGuard = 'default';
            $user = null;

            if ($defaultGuard = $app['auth']->getDefaultDriver()) {
                $currentGuard = $defaultGuard;
                $user = auth($currentGuard)->user();
            }

            if ($user) {
                //The cart name like `cart.{guard}.{user_id}`： cart.api.1
                $aliases = config('cart.aliases');

                if (isset($aliases[$currentGuard])) {
                    $currentGuard = $aliases[$currentGuard];
                }

                $cart->name($currentGuard . '.' . $user->id);

            } else {
                throw new Exception('Invalid auth.');
            }

            return $cart;
        });

        $this->app->alias(Cart::class, 'cart');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [Cart::class, 'cart'];
    }

    /**
     * load migration files.
     */
    protected function registerMigrations()
    {
        return $this->loadMigrationsFrom(__DIR__ . '/../migrations');
    }
}
