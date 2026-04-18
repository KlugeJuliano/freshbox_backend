<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    private function companyId(): string
    {
        return app('current_company')->id;
    }

    public function index(Request $request)
    {
        $products = Product::query()
            ->where('company_id', $this->companyId())
            ->when($request->integer('category_id'), fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower((string) $request->string('search')).'%']);
            })
            ->when($request->query('active') !== null, fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->with('category')
            ->orderBy('sort_order')
            ->paginate(30);

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();
        $data['company_id'] = $this->companyId();
        $data['slug'] = $this->uniqueSlug($data['name']);

        $product = Product::create($data);

        return (new ProductResource($product->load('category')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        $this->authorizeCompany($product);

        return new ProductResource($product->load(['images', 'category']));
    }

    public function update(StoreProductRequest $request, Product $product): ProductResource
    {
        $this->authorizeCompany($product);

        $data = $request->validated();

        if (($data['name'] ?? null) && $data['name'] !== $product->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $product->id);
        }

        $product->update($data);

        return new ProductResource($product->fresh()->load('category'));
    }

    public function destroy(Product $product)
    {
        $this->authorizeCompany($product);
        $product->delete();

        return response()->json(status: 204);
    }

    public function toggle(Product $product): ProductResource
    {
        $this->authorizeCompany($product);
        $product->update(['is_active' => ! $product->is_active]);

        return new ProductResource($product);
    }

    public function uploadImage(Request $request, Product $product, ImageService $imageService): ProductResource
    {
        $this->authorizeCompany($product);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ]);

        $product->update(
            $imageService->processProductImage(
                $request->file('image'),
                $this->companyId(),
                $product->id,
            )
        );

        return new ProductResource($product->fresh());
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $count = 1;

        while (
            Product::where('company_id', $this->companyId())
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$base}-{$count}";
            $count++;
        }

        return $slug;
    }

    private function authorizeCompany(Product $product): void
    {
        abort_if($product->company_id !== $this->companyId(), 403);
    }
}
