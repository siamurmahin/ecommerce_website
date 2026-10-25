<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Enums\MenuLocation;
use App\Support\HasLocalizedFields;
use App\Support\RevalidatesStorefront;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['location', 'label', 'label_en', 'url', 'is_highlighted', 'sort_order', 'is_active'])]
class MenuItem extends Model
{
    use HasLocalizedFields, RevalidatesStorefront;

    protected function casts(): array
    {
        return [
            'location' => MenuLocation::class,
            'is_highlighted' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function storefrontTags(): array
    {
        return ['menus'];
    }
}
