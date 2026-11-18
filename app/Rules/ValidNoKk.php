<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidNoKk implements ValidationRule
{
    /**
     * Run the validation rule.
     * Nomor Kartu Keluarga (KK) harus berupa 16 digit angka.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_numeric($value)) {
            $fail('Format :attribute harus berupa angka.');

            return;
        }

        $noKk = (string) $value;

        if (strlen($noKk) !== 16) {
            $fail(':attribute harus tepat 16 digit angka.');

            return;
        }

        if (! ctype_digit($noKk)) {
            $fail(':attribute hanya boleh berisi karakter angka.');

            return;
        }

        $kodeProvinsi = substr($noKk, 0, 2);
        if ($kodeProvinsi === '00') {
            $fail('Kode wilayah pada :attribute tidak valid.');

            return;
        }
    }
}
