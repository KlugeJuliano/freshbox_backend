<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoreResource;

class StoreController extends Controller
{
    public function show(): StoreResource
    {
        $store = app('current_company')->stores()->where('is_active', true)->firstOrFail();

        return new StoreResource($store);
    }
}
