<?php

namespace App\Domain\Catalog\Models;

use App\Support\HasLocalizedFields;
use App\Support\RevalidatesStorefront;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['attribute_id', 'value', 'value_en', 'color_hex', 'sort_order'])]
class AttributeValue extends Model
{
    use HasLocalizedFields, RevalidatesStorefront;

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
