<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

class ImageService
{
    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    public function processProductImage(UploadedFile $file, string $companyId, int $productId): array
    {
        $variants = [
            'thumb' => ['size' => 300, 'quality' => 80, 'key' => 'image_thumb_url'],
            'card' => ['size' => 600, 'quality' => 82, 'key' => 'image_card_url'],
            'full' => ['size' => 1200, 'quality' => 85, 'key' => 'image_full_url'],
        ];

        $urls = [];

        foreach ($variants as $name => $variant) {
            $processed = $this->manager
                ->decodePath($file->getPathname())
                ->scaleDown(width: $variant['size'], height: $variant['size'])
                ->encode(new JpegEncoder($variant['quality']));

            $path = "products/{$companyId}/{$productId}/{$name}.jpg";

            Storage::disk('s3')->put($path, (string) $processed, [
                'visibility' => 'public',
                'ContentType' => 'image/jpeg',
            ]);

            $urls[$variant['key']] = Storage::disk('s3')->url($path);
        }

        return $urls;
    }

    public function uploadBannerImage(UploadedFile $file, string $companyId, string $suffix = 'main'): string
    {
        $processed = $this->manager
            ->decodePath($file->getPathname())
            ->encode(new JpegEncoder(82));

        $path = "banners/{$companyId}/{$suffix}_".time().'.jpg';

        Storage::disk('s3')->put($path, (string) $processed, [
            'visibility' => 'public',
            'ContentType' => 'image/jpeg',
        ]);

        return Storage::disk('s3')->url($path);
    }

    public function uploadLogo(UploadedFile $file, string $companyId): string
    {
        $processed = $this->manager
            ->decodePath($file->getPathname())
            ->scaleDown(width: 400, height: 400)
            ->encode(new JpegEncoder(85));

        $path = "logos/{$companyId}/logo.jpg";

        Storage::disk('s3')->put($path, (string) $processed, [
            'visibility' => 'public',
            'ContentType' => 'image/jpeg',
        ]);

        return Storage::disk('s3')->url($path);
    }
}
