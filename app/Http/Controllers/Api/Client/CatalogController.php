<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    private function companyId(): string
    {
        return app('current_company')->id;
    }

    public function categories()
    {
        $categories = Category::query()
            ->where('company_id', $this->companyId())
            ->active()
            ->withCount(['products' => fn ($query) => $query->active()])
            ->paginate(24);

        return CategoryResource::collection($categories);
    }

    public function byCategory(string $slug)
    {
        $category = Category::query()
            ->where('company_id', $this->companyId())
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $products = Product::query()
            ->where('company_id', $this->companyId())
            ->where('category_id', $category->id)
            ->active()
            ->orderBy('sort_order')
            ->paginate(24);

        return ProductResource::collection($products);
    }

    public function show(string $slug): ProductResource
    {
        $product = Product::query()
            ->where('company_id', $this->companyId())
            ->where('slug', $slug)
            ->active()
            ->with(['images', 'category'])
            ->firstOrFail();

        return new ProductResource($product);
    }

    public function featured()
    {
        $products = Product::query()
            ->where('company_id', $this->companyId())
            ->active()
            ->featured()
            ->orderBy('sort_order')
            ->limit(10)
            ->get();

        return ProductResource::collection($products);
    }

    public function onPromo()
    {
        $products = Product::query()
            ->where('company_id', $this->companyId())
            ->active()
            ->onPromo()
            ->orderBy('sort_order')
            ->paginate(24);

        return ProductResource::collection($products);
    }

    public function search(Request $request)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $search = mb_strtolower($validated['q']);

        $products = Product::query()
            ->where('company_id', $this->companyId())
            ->active()
            ->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
            ->limit(30)
            ->get();

        return ProductResource::collection($products);
    }
}
