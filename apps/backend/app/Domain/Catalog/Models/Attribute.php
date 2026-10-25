<?php

namespace App\Domain\Catalog\Models;

use App\Support\HasLocalizedFields;
use App\Support\RevalidatesStorefront;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A variant dimension such as সাইজ or রং. */
#[Fillable(['name', 'name_en', 'slug', 'type', 'sort_order'])]
class Attribute extends Model
{
    use HasLocalizedFields, RevalidatesStorefront;

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('sort_order');
    }
}
