<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidNik implements ValidationRule
{
    /**
     * Run the validation rule.
     * NIK (Nomor Induk Kependudukan) harus terdiri dari 16 digit angka valid.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_numeric($value)) {
            $fail('Format :attribute harus berupa angka.');

            return;
        }

        $nik = (string) $value;

        if (strlen($nik) !== 16) {
            $fail(':attribute harus tepat 16 digit angka.');

            return;
        }

        if (! ctype_digit($nik)) {
            $fail(':attribute hanya boleh berisi karakter angka.');

            return;
        }

        // Validasi kode wilayah provinsi (tidak boleh 00)
        $kodeProvinsi = substr($nik, 0, 2);
        if ($kodeProvinsi === '00') {
            $fail('Kode wilayah pada :attribute tidak valid.');

            return;
        }
    }
}
