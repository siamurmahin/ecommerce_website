<?php

namespace App\Domain\Catalog\Feeds;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Settings\BrandSettings;
use App\Settings\FeedSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use XMLWriter;

/**
 * RSS 2.0 product feed with the Google `g:` namespace, which both Meta Commerce Manager and Google
 * Merchant Center read. One item per simple product, one per active variant (grouped by
 * item_group_id) for variable products. Prices include any running flash sale.
 */
final class ProductFeed
{
    public const CHANNELS = ['facebook' => 'Facebook / Instagram ক্যাটালগ', 'google' => 'Google Merchant Center'];

    public function __construct(private FeedSettings $settings) {}

    /** @return list<array<string, string|list<string>>> */
    public function items(): array
    {
        $locale = $this->settings->locale;
        $items = [];

        $this->query()->chunkById(200, function ($products) use (&$items, $locale) {
            foreach ($products as $product) {
                $base = $this->base($product, $locale);
                $variants = $product->isVariable() ? $product->variants->where('is_active', true) : collect();

                if ($product->isVariable()) {
                    foreach ($variants as $variant) {
                        $items[] = $this->item($product, $base, $variant, $locale);
                    }
                } else {
                    $items[] = $this->item($product, $base, null, $locale);
                }
            }
        });

        return array_values(array_filter($items, fn ($i) => $this->settings->include_out_of_stock || $i['availability'] === 'in_stock'));
    }

    public function xml(string $channel): string
    {
        $w = new XMLWriter;
        $w->openMemory();
        $w->startDocument('1.0', 'UTF-8');
        $w->startElement('rss');
        $w->writeAttribute('version', '2.0');
        $w->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
        $w->startElement('channel');
        $w->writeElement('title', app(BrandSettings::class)->name);
        $w->writeElement('link', $this->storefront('/'));
        $w->writeElement('description', self::CHANNELS[$channel] ?? 'Products');

        foreach ($this->items() as $item) {
            $w->startElement('item');
            foreach ($item as $key => $value) {
                if ($key === 'google_product_category' && $channel !== 'google') {
                    continue;
                }
                foreach ((array) $value as $v) {
                    $w->writeElement("g:{$key}", $v);
                }
            }
            $w->endElement();
        }

        $w->endElement();
        $w->endElement();
        $w->endDocument();

        return $w->outputMemory();
    }

    /** @return Builder<Product> */
    private function query()
    {
        $categories = $this->settings->category_ids;
        if ($categories !== []) {
            $categories = Category::whereIn('id', $categories)->get()->flatMap(fn (Category $c) => [$c->id, ...$c->descendantIds()])->unique()->all();
        }

        return Product::visible()
            ->with(['images', 'brand', 'category.parent', 'variants.attributeValues'])
            ->when($categories !== [], fn ($q) => $q->whereIn('category_id', $categories));
    }

    /** Fields shared by a product and all its variants. @return array<string, string|list<string>> */
    private function base(Product $product, string $locale): array
    {
        $images = $product->images->map(fn ($i) => Storage::disk('public')->url($i->path))->values();
        $description = Str::of(strip_tags((string) ($product->localized('description', $locale) ?: $product->localized('short_description', $locale))))
            ->replaceMatches('/\s+/u', ' ')->trim()->limit(4900, '…')->toString();

        return array_filter([
            'description' => $description ?: (string) $product->localized('name', $locale),
            'link' => $this->storefront("/p/{$product->slug}", $locale),
            'image_link' => $images->first(),
            'additional_image_link' => $images->slice(1, 10)->values()->all(),
            'brand' => $product->brand?->name ?? $this->settings->brand_fallback ?? app(BrandSettings::class)->name,
            'condition' => 'new',
            'product_type' => collect([$product->category?->parent, $product->category])->filter()->map(fn ($c) => $c->localized('name', $locale))->implode(' > '),
            'google_product_category' => $this->settings->google_category,
            'identifier_exists' => 'no',
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /** @return array<string, string|list<string>> */
    private function item(Product $product, array $base, ?ProductVariant $variant, string $locale): array
    {
        $regular = $variant?->price ?? $product->price;
        $compare = $variant?->compare_price ?? $product->compare_price;
        $selling = $product->unitPrice($variant);
        $listPrice = max($regular, (int) $compare);
        $stock = $variant?->stock ?? $product->stock;
        $title = (string) $product->localized('name', $locale);
        $label = $variant?->label($locale);

        return [
            'id' => $variant ? ($variant->sku ?: "P{$product->id}-V{$variant->id}") : ($product->sku ?: "P{$product->id}"),
            ...($variant ? ['item_group_id' => $product->sku ?: "P{$product->id}"] : []),
            'title' => Str::limit($label ? "{$title} — {$label}" : $title, 150, ''),
            ...$base,
            ...($variant?->image ? ['image_link' => Storage::disk('public')->url($variant->image)] : []),
            'availability' => $stock > 0 ? 'in_stock' : 'out_of_stock',
            'price' => self::money($listPrice),
            ...($selling < $listPrice ? ['sale_price' => self::money($selling)] : []),
        ];
    }

    private static function money(int $poisha): string
    {
        return number_format($poisha / 100, 2, '.', '').' BDT';
    }

    private function storefront(string $path, string $locale = 'bn'): string
    {
        $prefix = $locale === 'bn' ? '' : "/{$locale}";

        return rtrim((string) config('shop.storefront_url'), '/').$prefix.($path === '/' && $prefix ? '' : $path);
    }
}
