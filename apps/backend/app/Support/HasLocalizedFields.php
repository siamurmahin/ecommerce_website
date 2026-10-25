<?php

namespace App\Support;

/**
 * Bangla is the primary copy column (`name`); English lives in `name_en`
 * and falls back to Bangla when empty.
 */
trait HasLocalizedFields
{
    public function localized(string $field, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        if ($locale === 'en' && filled($this->getAttribute("{$field}_en"))) {
            return $this->getAttribute("{$field}_en");
        }

        return $this->getAttribute($field);
    }
}
