<?php

namespace App\Domain\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A business cost outside the products themselves (ads, packaging, salary…), subtracted for net profit. Amount in poisha. */
#[Fillable(['category', 'title', 'campaign', 'amount', 'spent_on', 'note', 'user_id'])]
class Expense extends Model
{
    public const CATEGORIES = [
        'ads' => 'বিজ্ঞাপন (ফেসবুক/গুগল)',
        'delivery' => 'ডেলিভারি / কুরিয়ার',
        'packaging' => 'প্যাকেজিং',
        'salary' => 'বেতন',
        'rent' => 'ভাড়া',
        'utility' => 'বিল / ইন্টারনেট',
        'other' => 'অন্যান্য',
    ];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'spent_on' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }
}
