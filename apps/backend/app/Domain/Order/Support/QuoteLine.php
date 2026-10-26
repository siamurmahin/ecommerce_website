<?php

namespace App\Domain\Order\Support;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;

final class QuoteLine
{
    public const UNAVAILABLE = 'unavailable';

    public const INSUFFICIENT_STOCK = 'insufficient_stock';

    /** Most units of one item a single pre-order may ask for. */
    public const MAX_PREORDER = 20;

    public function __construct(
        public readonly int $productId,
        public readonly ?int $variantId,
        public readonly int $quantity,
        public readonly ?Product $product,
        public readonly ?ProductVariant $variant,
        public readonly int $unitPrice,
        public readonly int $available,
        public readonly ?string $problem,
        public readonly ?int $bumpId = null,
        /** Ordered beyond stock on a pre-order product; no stock is taken for it. */
        public readonly bool $preorder = false,
    ) {}

    public function lineTotal(): int
    {
        return $this->unitPrice * $this->quantity;
    }

    /** Variant image first, then the product cover; a storage path on the public disk. */
    public function imagePath(): ?string
    {
        return $this->variant?->image ?? $this->product?->images->first()?->path;
    }
}
