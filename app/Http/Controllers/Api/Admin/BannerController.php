<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBannerRequest;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use App\Services\ImageService;

class BannerController extends Controller
{
    private function companyId(): string
    {
        return app('current_company')->id;
    }

    public function index()
    {
        $banners = Banner::query()
            ->where('company_id', $this->companyId())
            ->orderByDesc('priority')
            ->get();

        return BannerResource::collection($banners);
    }

    public function store(StoreBannerRequest $request, ImageService $imageService)
    {
        $data = $request->validated();
        $data['company_id'] = $this->companyId();
        $data['image_url'] = $imageService->uploadBannerImage($request->file('image'), $this->companyId());

        if ($request->hasFile('image_mobile')) {
            $data['image_mobile_url'] = $imageService->uploadBannerImage(
                $request->file('image_mobile'),
                $this->companyId(),
                'mobile'
            );
        }

        unset($data['image'], $data['image_mobile']);

        $banner = Banner::create($data);

        return (new BannerResource($banner))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Banner $banner): BannerResource
    {
        $this->authorizeCompany($banner);

        return new BannerResource($banner);
    }

    public function update(StoreBannerRequest $request, Banner $banner, ImageService $imageService): BannerResource
    {
        $this->authorizeCompany($banner);

        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image_url'] = $imageService->uploadBannerImage($request->file('image'), $this->companyId());
        }

        if ($request->hasFile('image_mobile')) {
            $data['image_mobile_url'] = $imageService->uploadBannerImage(
                $request->file('image_mobile'),
                $this->companyId(),
                'mobile'
            );
        }

        unset($data['image'], $data['image_mobile']);

        $banner->update($data);

        return new BannerResource($banner->fresh());
    }

    public function destroy(Banner $banner)
    {
        $this->authorizeCompany($banner);
        $banner->delete();

        return response()->json(status: 204);
    }

    public function toggle(Banner $banner): BannerResource
    {
        $this->authorizeCompany($banner);
        $banner->update(['is_active' => ! $banner->is_active]);

        return new BannerResource($banner);
    }

    private function authorizeCompany(Banner $banner): void
    {
        abort_if($banner->company_id !== $this->companyId(), 403);
    }
}
