<?php

namespace Uuu9\Signature;

use Illuminate\Support\ServiceProvider;
use Uuu9\Signature\Middleware\Authenticate;

class SignServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    public function boot()
    {
        //注册配置
        $this->app->configure('api_sign');
        $path = dirname(__DIR__).'/src/config/api.php';
        $this->mergeConfigFrom($path, 'api_sign');

        //注册中间件
        $this->app->routeMiddleware([
            'auth' => Authenticate::class
        ]);
    }

    public function register()
    {
    }
}
