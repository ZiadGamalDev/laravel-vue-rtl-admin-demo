<?php

/** Every validation message Laravel ships in English has an Arabic version. */
it('has an Arabic message for every English validation rule', function () {
    $en = require __DIR__.'/../../lang/en/validation.php';
    $ar = require __DIR__.'/../../lang/ar/validation.php';

    $flatten = function (array $lines, string $prefix = '') use (&$flatten): array {
        $keys = [];
        foreach ($lines as $key => $value) {
            if (in_array($key, ['custom', 'attributes'], true)) {
                continue;
            }
            $keys = array_merge($keys, is_array($value) ? $flatten($value, "$prefix$key.") : ["$prefix$key"]);
        }

        return $keys;
    };

    expect(array_diff($flatten($en), $flatten($ar)))->toBe([]);
});

it('has the same app message keys in both languages', function () {
    foreach (['auth', 'messages'] as $file) {
        $en = require __DIR__."/../../lang/en/$file.php";
        $ar = require __DIR__."/../../lang/ar/$file.php";
        expect(array_keys($ar))->toEqualCanonicalizing(array_keys($en));
    }
});
