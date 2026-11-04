<?php

use App\Support\ArabicText;

it('normalises Arabic spelling variants', function (string $input, string $expected) {
    expect(ArabicText::normalize($input))->toBe($expected);
})->with([
    'hamza on alef' => ['أحمد', 'احمد'],
    'hamza under alef' => ['إيمان', 'ايمان'],
    'madda' => ['آمنة', 'امنه'],
    'taa marbuta' => ['فاطمة', 'فاطمه'],
    'alef maqsura' => ['مصطفى', 'مصطفي'],
    'ya and waw with hamza' => ['هيئة مؤسسة', 'هييه موسسه'],
    'tashkeel and shadda' => ['مُحَمَّد', 'محمد'],
    'tatweel' => ['ســـارة', 'ساره'],
    'arabic-indic digits' => ['٠١٢٣٤٥٦٧٨٩', '0123456789'],
    'latin case and spaces' => ['  Ahmed   ELSAYED ', 'ahmed elsayed'],
]);
