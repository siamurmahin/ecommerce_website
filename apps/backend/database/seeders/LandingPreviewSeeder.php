<?php

namespace Database\Seeders;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
use App\Domain\Marketing\Models\LandingPage;
use App\Domain\Marketing\Support\LandingBlocks;
use App\Domain\Marketing\Support\LandingTemplates;
use Illuminate\Database\Seeder;

/**
 * Sample landing pages the admin preview screenshots are taken from (`pnpm landing:previews` in the storefront):
 * one `preview-{design}` page per ready-made design and `preview-blocks` with every block type, filled with
 * demo text and the catalogue's images. Unpublished from search; safe to re-run.
 */
class LandingPreviewSeeder extends Seeder
{
    public function run(): void
    {
        $product = Product::visible()->whereHas('images')->withCount('images')->orderByDesc('images_count')->first();
        if (! $product) {
            $this->command?->warn('No product with images — seed the demo catalogue first.');

            return;
        }

        $images = ProductImage::query()->latest('id')->limit(6)->pluck('path')->all();

        foreach (LandingTemplates::TEMPLATES as $key => $template) {
            $this->page("preview-{$key}", "প্রিভিউ — {$template['label']}", $key, $product, $this->fill(LandingTemplates::blocks($key), $images));
        }

        $this->page('preview-blocks', 'প্রিভিউ — সব ব্লক', 'showcase', $product, $this->fill($this->everyBlock(), $images));
    }

    private function page(string $slug, string $title, string $template, Product $product, array $blocks): void
    {
        LandingPage::updateOrCreate(['slug' => $slug], [
            'title' => $title, 'product_id' => $product->id, 'template' => $template,
            'is_active' => true, 'is_indexable' => false, 'blocks' => $blocks,
        ]);
    }

    /** One of each block type, in the order the block picker lists them. */
    private function everyBlock(): array
    {
        $byType = collect(array_keys(LandingTemplates::TEMPLATES))
            ->flatMap(fn (string $key) => LandingTemplates::blocks($key))
            ->reverse()->keyBy('type'); // the first design that uses a type wins

        return collect(LandingBlocks::TYPES)->map(fn (string $type) => $byType[$type] ?? ['type' => $type, 'data' => ['enabled' => true]])
            ->map(function (array $block) {
                $d = &$block['data'];
                match ($block['type']) {
                    'text' => $d['content'] ??= '<p>পণ্যটি কী দিয়ে তৈরি, কীভাবে ব্যবহার করবেন আর কেন এটা আলাদা — দুই-তিন লাইনে।</p>',
                    'video' => $d['url'] = 'https://www.youtube.com/watch?v=aqz-KE-bpKQ',
                    'cta' => $d['title'] ??= 'অফার শেষ হওয়ার আগেই নিয়ে নিন',
                    'order_form' => $d['packages'] = [
                        ['label' => '১টি', 'badge' => 'জনপ্রিয়', 'quantity' => 1, 'price' => 0],
                        ['label' => '২টি (স্পেশাল গিফট সহ)', 'badge' => 'সেরা সেভিংস', 'quantity' => 2, 'price' => 0],
                    ],
                    default => null,
                };

                return $block;
            })->values()->all();
    }

    /** Demo media and reviews for the blocks that start empty; package prices follow the product's price. */
    private function fill(array $blocks, array $images): array
    {
        $price = Product::visible()->whereHas('images')->withCount('images')->orderByDesc('images_count')->value('price') ?? 100000;

        return array_map(function (array $block) use ($images, $price) {
            $d = &$block['data'];
            match ($block['type']) {
                'gallery' => $d['images'] = array_slice($images, 0, 5),
                'offer' => $d['ends_at'] = now()->addDays(2)->addHours(5)->toDateTimeString(),
                'checklist' => $d['items'] = collect([
                    ['প্রিমিয়াম পণ্য', 'মূল পণ্য, যত্নে প্যাক করা।'],
                    ['উপহারের বাক্স', 'সুন্দর বাক্সে, উপহার দিতে প্রস্তুত।'],
                    ['ব্যবহারের নির্দেশিকা', 'কীভাবে ব্যবহার আর যত্ন নেবেন।'],
                ])->map(fn (array $item, int $i) => ['title' => $item[0], 'note' => $item[1], 'image' => $images[$i] ?? null])->all(),
                'reviews' => $d['items'] = [
                    ['name' => 'জাহাঙ্গীর আলম', 'location' => 'ঢাকা', 'rating' => 5, 'text' => 'চমৎকার মান! দুই দিনেই হাতে পেয়েছি, প্যাকিংও সুন্দর ছিল।', 'image' => null],
                    ['name' => 'নুসরাত জাহান', 'location' => 'চট্টগ্রাম', 'rating' => 5, 'text' => 'ছবিতে যেমন দেখেছি, হুবহু তেমনই। আবার নেবো।', 'image' => null],
                    ['name' => 'রাকিবুল হাসান', 'location' => 'সিলেট', 'rating' => 4, 'text' => 'দামের তুলনায় খুব ভালো।', 'image' => null],
                ],
                'order_form' => $d['packages'] = array_map(fn (array $p) => [...$p, 'price' => $p['price'] ?: $price * $p['quantity'] - ($p['quantity'] - 1) * intdiv($price, 10)], $d['packages'] ?? []),
                default => null,
            };

            return $block;
        }, $blocks);
    }
}
