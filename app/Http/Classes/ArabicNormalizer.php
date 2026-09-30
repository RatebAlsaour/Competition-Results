<?php

namespace App\Http\Classes;

/**
 * Normalizes Arabic text for searching (same rules as the portal's JS normalize()).
 */
class ArabicNormalizer
{
    /**
     * Trim and collapse whitespace (including non-breaking spaces and direction marks).
     */
    public static function clean(mixed $value): string
    {
        if ($value === null || $value instanceof \DateTimeInterface)
        {
            return '';
        }
        return trim(preg_replace('/[\s\x{00A0}\x{200F}\x{200E}]+/u', ' ', (string) $value));
    }

    /**
     * Search key: removes diacritics/tatweel and unifies hamza forms, taa marbuta and alef maqsura.
     */
    public static function searchKey(mixed $value): string
    {
        $text = self::clean($value);
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text);
        $text = strtr($text, [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي',
        ]);
        return mb_strtolower($text);
    }
}
