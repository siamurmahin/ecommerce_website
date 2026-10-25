<?php

namespace App\Domain\Marketing\Support;

use App\Domain\Marketing\Models\FlashSaleItem;
use Illuminate\Support\Collection;

/**
 * Running flash-sale items keyed by product, loaded once and reused for every price on the
 * request. A product in two running sales takes the one that ends first.
 */
final class ActiveFlashSales
{
    /** @var Collection<int, FlashSaleItem>|null */
    private ?Collection $items = null;

    private ?int $loadedAt = null;

    public function for(int $productId): ?FlashSaleItem
    {
        return $this->items()->get($productId);
    }

    public function price(int $productId, int $base): int
    {
        return $this->for($productId)?->apply($base) ?? $base;
    }

    /** @return list<int> */
    public function productIds(): array
    {
        return $this->items()->keys()->all();
    }

    public function flush(): void
    {
        $this->items = null;
    }

    /** @return Collection<int, FlashSaleItem> */
    private function items(): Collection
    {
        // Long-lived processes (queue workers) re-read after a minute so sale boundaries apply.
        if ($this->items === null || $this->loadedAt < now()->subMinute()->getTimestamp()) {
            $this->items = FlashSaleItem::query()
                ->with('flashSale')
                ->whereHas('flashSale', fn ($q) => $q->running())
                ->get()
                ->sortBy(fn (FlashSaleItem $item) => $item->flashSale->ends_at)
                ->unique('product_id')
                ->keyBy('product_id');
            $this->loadedAt = now()->getTimestamp();
        }

        return $this->items;
    }
}
