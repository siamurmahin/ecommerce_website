<?php

namespace App\Domain\Content\Models;

use App\Support\HasLocalizedFields;
use App\Support\RevalidatesStorefront;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Static content page (about, FAQ, policies…), edited in admin as rich text. */
#[Fillable(['slug', 'title', 'title_en', 'content', 'content_en', 'meta_title', 'meta_description', 'is_active'])]
class Page extends Model
{
    use HasLocalizedFields, RevalidatesStorefront;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function storefrontTags(): array
    {
        // A renamed slug must drop the old URL's cache too.
        $old = $this->getOriginal('slug');

        return array_values(array_unique(array_filter(['pages', "page:{$this->slug}", $old ? "page:{$old}" : null])));
    }
}
