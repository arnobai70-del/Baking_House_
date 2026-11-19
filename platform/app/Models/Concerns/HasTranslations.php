<?php

namespace App\Models\Concerns;

/**
 * Bangla text lives in `<field>_bn` columns on the same row (no duplicated records).
 * localized() returns the Bangla value when the storefront language is Bangla and a translation
 * exists, otherwise the English value.
 */
trait HasTranslations
{
    public function localized(string $field): ?string
    {
        if (app()->getLocale() === 'bn' && array_key_exists($field.'_bn', $this->getAttributes())) {
            $translated = $this->getAttribute($field.'_bn');
            if (is_string($translated) && trim($translated) !== '') {
                return $translated;
            }
        }

        return $this->getAttribute($field);
    }
}
