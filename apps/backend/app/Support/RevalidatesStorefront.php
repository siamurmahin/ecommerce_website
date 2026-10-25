<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/** Refreshes the storefront's cached copy when the model is saved or deleted. */
trait RevalidatesStorefront
{
    protected static function bootRevalidatesStorefront(): void
    {
        $revalidate = fn (Model $model) => StorefrontCache::revalidate(...$model->storefrontTags());

        static::saved($revalidate);
        static::deleted($revalidate);
    }

    /**
     * Storefront cache tags this model feeds (see storefront lib/api/*.ts).
     *
     * @return list<string>
     */
    public function storefrontTags(): array
    {
        return ['catalog'];
    }
}
