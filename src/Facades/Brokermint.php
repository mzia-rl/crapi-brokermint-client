<?php

namespace Canzell\Facades;

use Illuminate\Support\Facades\Facade;
use Canzell\Http\Clients\BrokermintClient;

class Brokermint extends Facade
{

    static public function getFacadeAccessor()
    {
        return BrokermintClient::class;
    }

}