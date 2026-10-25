<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Enums\BannerPlacement;
use App\Support\HasLocalizedFields;
use App\Support\RevalidatesStorefront;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'placement', 'title', 'title_en', 'subtitle', 'subtitle_en', 'image', 'image_mobile',
    'link', 'sort_order', 'is_active', 'starts_at', 'ends_at',
])]
class Banner extends Model
{
    use HasLocalizedFields, RevalidatesStorefront;

    protected function casts(): array
    {
        return [
            'placement' => BannerPlacement::class,
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** Active and inside its optional schedule window. */
    public function scopeLive(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function storefrontTags(): array
    {
        return ['home'];
    }
}
