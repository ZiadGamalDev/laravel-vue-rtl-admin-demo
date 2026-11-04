<?php

namespace App\Support;

use Illuminate\Support\Facades\App;

/** Server-side formatting that matches the SPA (Latin digits in documents for portability). */
class Money
{
    public static function format(float|string $amount): string
    {
        $symbol = config('app.currency') === 'USD' ? '$' : config('app.currency');
        $value = number_format((float) $amount, 2);

        return App::getLocale() === 'ar' ? $value.' '.$symbol : $symbol.$value;
    }

    public static function date(\DateTimeInterface $date): string
    {
        return $date->locale(App::getLocale())->translatedFormat('j F Y');
    }
}
