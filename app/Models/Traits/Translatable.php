<?php

namespace App\Models\Traits;

trait Translatable
{
    public function translate(string $field, string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();
        $suffix = $locale === 'en' ? '_en' : '_es';
        $fieldName = $field . $suffix;

        return $this->$fieldName ?? null;
    }
}
