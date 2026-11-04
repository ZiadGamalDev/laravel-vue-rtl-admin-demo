<?php

namespace App\Support;

/**
 * Normalises Arabic text for search, so people find "أحمد" by typing "احمد",
 * "فاطمة" by "فاطمه" and "مُصطفى" by "مصطفي". Applied to both the stored
 * search text and the user's query.
 */
class ArabicText
{
    private const LETTERS = [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', // alef forms
        'ى' => 'ي', 'ئ' => 'ي',                        // alef maqsura / ya with hamza
        'ؤ' => 'و',                                     // waw with hamza
        'ة' => 'ه',                                     // taa marbuta
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    public static function normalize(?string $text): string
    {
        $text = (string) $text;
        // Tashkeel (harakat, shadda, sukun, tanween), superscript alef and tatweel.
        $text = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{0640}]/u', '', $text);
        $text = strtr($text, self::LETTERS);
        $text = mb_strtolower($text);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /** The searchable text stored for a customer. */
    public static function customerSearchText(?string $name, ?string $email, ?string $phone, ?string $city): string
    {
        return self::normalize(implode(' ', array_filter([$name, $email, $phone, $city])));
    }
}
