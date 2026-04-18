<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;

class DashboardController extends Controller
{
    public function index()
    {
        $companyId = app('current_company')->id;

        return response()->json([
            'categories' => Category::where('company_id', $companyId)->count(),
            'products' => Product::where('company_id', $companyId)->count(),
            'banners' => Banner::where('company_id', $companyId)->count(),
            'orders' => [
                'new' => Order::where('company_id', $companyId)->where('status', 'new')->count(),
                'today' => Order::where('company_id', $companyId)->whereDate('created_at', today())->count(),
            ],
        ]);
    }
}
