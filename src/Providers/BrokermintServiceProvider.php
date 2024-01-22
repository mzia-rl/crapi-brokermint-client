<?php

namespace Canzell\Providers;

use Illuminate\Support\ServiceProvider;
use Canzell\Http\Clients\BrokermintClient;

class BrokermintServiceProvider extends ServiceProvider
{

    public $singletons = [
        BrokermintClient::class => BrokermintClient::class 
    ];

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../../config/brokermint-client.php' => config_path('brokermint-client.php')
        ]);
        $this->mergeConfigFrom(
            __DIR__.'/../../config/brokermint-client.php', 'brokermint-client'
        );
    }

}
