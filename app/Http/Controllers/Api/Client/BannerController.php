<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Models\Banner;

class BannerController extends Controller
{
    public function index()
    {
        $banners = Banner::query()
            ->where('company_id', app('current_company')->id)
            ->active()
            ->get();

        return BannerResource::collection($banners);
    }
}
