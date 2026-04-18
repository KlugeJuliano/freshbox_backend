<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    private function companyId(): string
    {
        return app('current_company')->id;
    }

    public function index()
    {
        $categories = Category::query()
            ->where('company_id', $this->companyId())
            ->withCount('products')
            ->orderBy('sort_order')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function store(StoreCategoryRequest $request)
    {
        $data = $request->validated();
        $data['company_id'] = $this->companyId();
        $data['slug'] = $this->uniqueSlug($data['name']);

        $category = Category::create($data);

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Category $category): CategoryResource
    {
        $this->authorizeCompany($category);

        return new CategoryResource($category->loadCount('products'));
    }

    public function update(StoreCategoryRequest $request, Category $category): CategoryResource
    {
        $this->authorizeCompany($category);

        $data = $request->validated();

        if (($data['name'] ?? null) && $data['name'] !== $category->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $category->id);
        }

        $category->update($data);

        return new CategoryResource($category->fresh()->loadCount('products'));
    }

    public function destroy(Category $category)
    {
        $this->authorizeCompany($category);
        $category->delete();

        return response()->json(status: 204);
    }

    public function toggle(Category $category): CategoryResource
    {
        $this->authorizeCompany($category);
        $category->update(['is_active' => ! $category->is_active]);

        return new CategoryResource($category);
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($validated['items'] as $item) {
            Category::query()
                ->where('company_id', $this->companyId())
                ->whereKey($item['id'])
                ->update(['sort_order' => $item['sort_order']]);
        }

        return CategoryResource::collection(
            Category::where('company_id', $this->companyId())->orderBy('sort_order')->get()
        );
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $count = 1;

        while (
            Category::where('company_id', $this->companyId())
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$base}-{$count}";
            $count++;
        }

        return $slug;
    }

    private function authorizeCompany(Category $category): void
    {
        abort_if($category->company_id !== $this->companyId(), 403);
    }
}
