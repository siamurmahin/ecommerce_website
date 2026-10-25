<?php

namespace Database\Seeders;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Content\Enums\BannerPlacement;
use App\Domain\Content\Models\Banner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * LOCAL ONLY. Swaps the demo placeholders for images saved from the laravelpro.projoss.com
 * reference site (storage/app/public/reference, never committed). They belong to that site
 * and don't match our products; replace them with our own photos before launch.
 */
class ReferenceImageSeeder extends Seeder
{
    public function run(): void
    {
        $disk = Storage::disk('public');
        $products = $disk->files('reference/products');
        $categories = $disk->files('reference/categories');

        if ($products === []) {
            $this->command?->warn('No reference images in storage/app/public/reference; skipping.');

            return;
        }

        Product::orderBy('id')->get()->each(function (Product $product, int $i) use ($products) {
            $product->images()->delete();
            foreach ([0, 1] as $n) {
                $product->images()->create([
                    'path' => $products[($i + $n) % count($products)],
                    'sort_order' => $n,
                ]);
            }
        });

        Category::orderBy('id')->get()->each(
            fn (Category $category, int $i) => $category->update(['image' => $categories[$i % count($categories)] ?? null])
        );

        $banners = [
            [BannerPlacement::Hero, 'reference/banners/ff237e70-9ee2-4b45-9d8b-b903b6d1bfee.webp', 'ঈদ কালেকশন ২০২৬', 'Eid Collection 2026', '/shop?badge=new'],
            [BannerPlacement::Hero, 'reference/banners/d3c8e5ac-4a33-4cfb-8cc4-f26ce5929cc4.webp', 'সব পণ্যে বিশেষ ছাড়', 'Special offers', '/shop?badge=hot'],
            [BannerPlacement::Promo, 'reference/banners/7e03de8f-1f70-49cc-b444-70639abc770b.png', 'আতর ও সুগন্ধি', 'Attar & fragrance', '/c/attar'],
            [BannerPlacement::Promo, 'reference/banners/5ad0f583-5207-4c94-8eb9-2233ff5ba137.jpeg', 'ইবাদত সামগ্রী', 'Prayer essentials', '/c/prayer-essentials'],
        ];

        foreach ($banners as $order => [$placement, $image, $title, $titleEn, $link]) {
            if (! $disk->exists($image)) {
                continue;
            }

            Banner::updateOrCreate(['image' => $image], [
                'placement' => $placement,
                'title' => $title,
                'title_en' => $titleEn,
                'link' => $link,
                'sort_order' => $order,
                'is_active' => true,
            ]);
        }
    }
}
