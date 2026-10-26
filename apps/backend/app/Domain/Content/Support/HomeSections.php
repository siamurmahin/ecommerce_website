<?php

namespace App\Domain\Content\Support;

use App\Domain\Catalog\Enums\ProductBadge;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Marketing\Support\ActiveFlashSales;
use App\Settings\HomeSettings;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Turns the homepage builder's blocks into ready-to-render sections: copy localized,
 * products and categories loaded. Hidden blocks and empty product rows are dropped.
 */
final class HomeSections
{
    public const TYPES = ['hero', 'trust', 'categories', 'products', 'promo_banners', 'help', 'text'];

    public const SOURCES = [
        'featured' => 'বাছাই করা (Featured)',
        'deals' => 'অফার / ডিসকাউন্ট',
        'flash_sale' => 'চলমান ফ্ল্যাশ সেল (কাউন্টডাউনসহ)',
        'new' => 'নতুন এসেছে',
        'best_sellers' => 'বেস্ট সেলার',
        'category' => 'নির্দিষ্ট ক্যাটাগরি',
    ];

    /** Product ids already shown higher up, so rows never repeat a product. */
    private array $shown = [];

    public function __construct(private HomeSettings $settings) {}

    /** @return list<array<string, mixed>> */
    public function resolve(): array
    {
        $sections = [];

        foreach ($this->settings->sections as $i => $block) {
            $type = $block['type'] ?? null;
            $data = $block['data'] ?? [];

            if (! in_array($type, self::TYPES, true) || ! ($data['enabled'] ?? true)) {
                continue;
            }

            $section = [
                'id' => "{$i}-{$type}",
                'type' => $type,
                'title' => $this->text($data, 'title'),
                'note' => $this->text($data, 'note'),
                'eyebrow' => $this->text($data, 'eyebrow'),
                'subtitle' => $this->text($data, 'subtitle'),
                'button_label' => $this->text($data, 'button_label'),
                'button_url' => $data['button_url'] ?? null,
                'link_url' => $data['link_url'] ?? null,
                'layout' => ($data['layout'] ?? 'grid') === 'carousel' ? 'carousel' : 'grid',
                'columns' => (int) ($data['columns'] ?? 4) === 5 ? 5 : 4,
                'style' => in_array($data['style'] ?? null, ['panel', 'highlight'], true) ? $data['style'] : 'plain',
                'badge' => ProductBadge::tryFrom((string) ($data['badge'] ?? ''))?->value,
                'content' => ($html = $this->text($data, 'content')) ? RichContentRenderer::make($html)->toHtml() : null,
                'items' => collect($data['items'] ?? [])->map(fn (array $item) => [
                    'icon' => $item['icon'] ?? 'star',
                    'title' => $this->text($item, 'title') ?? '',
                    'note' => $this->text($item, 'note'),
                ])->values()->all(),
                'products' => collect(),
                'categories' => collect(),
                'ends_at' => null,
            ];

            match ($type) {
                // The hero's text layout (used when no hero banner is live) shows two featured products.
                'hero' => $section['products'] = $this->products('featured', null, 2, track: false),
                'products' => $section['products'] = $this->products($data['source'] ?? 'new', $data['category_id'] ?? null, (int) ($data['limit'] ?? 8)),
                'categories' => $section['categories'] = Category::active()->whereNull('parent_id')
                    ->orderByDesc('is_featured')->orderBy('sort_order')->limit(max(1, min((int) ($data['limit'] ?? 7), 14)))->get(),
                default => null,
            };

            if ($type === 'products' && ($data['source'] ?? null) === 'flash_sale') {
                $section['ends_at'] = $section['products']
                    ->map(fn (Product $p) => $p->flashSaleItem()?->flashSale->ends_at)->filter()->min()?->toIso8601String();
            }

            if ($type === 'products' && $section['products']->isEmpty()) {
                continue;
            }

            $sections[] = $section;
        }

        return $sections;
    }

    /** @return Collection<int, Product> */
    private function products(string $source, mixed $categoryId, int $limit, bool $track = true): Collection
    {
        $query = Product::visible()->with(['images', 'category', 'variants'])->whereNotIn('id', $this->shown);

        match ($source) {
            'featured' => $query->where('is_featured', true)->latest('id'),
            // Anything marked down, on the product or on an active variant.
            'deals' => $query->where(fn (Builder $q) => $q
                ->whereColumn('compare_price', '>', 'price')
                ->orWhereHas('variants', fn (Builder $v) => $v->where('is_active', true)->whereColumn('compare_price', '>', 'price')))
                ->orderByDesc('sold_count'),
            'flash_sale' => $query->whereIn('id', app(ActiveFlashSales::class)->productIds())->orderByDesc('sold_count'),
            'best_sellers' => $query->where(fn (Builder $q) => $q->where('badge', ProductBadge::BestSeller)->orWhere('sold_count', '>', 0))->orderByDesc('sold_count'),
            'category' => ($category = Category::active()->find($categoryId))
                ? $query->whereIn('category_id', $category->descendantIds())->latest('id')
                : $query->whereRaw('1 = 0'),
            default => $query->latest('published_at')->latest('id'),
        };

        $items = $query->limit(max(1, min($limit, 20)))->get();

        if ($track) {
            $this->shown = [...$this->shown, ...$items->pluck('id')];
        }

        return $items;
    }

    private function text(array $data, string $field): ?string
    {
        $value = app()->getLocale() === 'en' && filled($data["{$field}_en"] ?? null) ? $data["{$field}_en"] : ($data[$field] ?? null);

        return filled($value) ? (string) $value : null;
    }
}
