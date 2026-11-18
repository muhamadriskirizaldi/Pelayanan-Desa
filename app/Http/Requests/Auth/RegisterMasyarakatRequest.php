<?php

namespace App\Http\Requests\Auth;

use App\Enums\Gender;
use App\Rules\ValidNik;
use App\Rules\ValidNoKk;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterMasyarakatRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Data Akun Pengguna
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'min:3', 'max:50', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'phone_number' => ['nullable', 'string', 'max:20', 'regex:/^(\+62|62|0)8[1-9][0-9]{6,10}$/'],

            // Data Demografis Kependudukan (Warga)
            'nik' => ['required', 'string', new ValidNik, 'unique:resident_profiles,nik'],
            'no_kk' => ['required', 'string', new ValidNoKk],
            'tempat_lahir' => ['required', 'string', 'max:100'],
            'tanggal_lahir' => ['required', 'date', 'before:today'],
            'jenis_kelamin' => ['required', Rule::enum(Gender::class)],

            // Alamat Domisili
            'alamat' => ['required', 'string', 'max:500'],
            'rt' => ['nullable', 'string', 'max:5'],
            'rw' => ['nullable', 'string', 'max:5'],
            'desa' => ['required', 'string', 'max:100'],
            'kecamatan' => ['required', 'string', 'max:100'],
            'kabupaten' => ['required', 'string', 'max:100'],
            'provinsi' => ['required', 'string', 'max:100'],
            'kode_pos' => ['nullable', 'string', 'digits:5'],
        ];
    }

    /**
     * Pesan kesalahan khusus dalam Bahasa Indonesia.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap wajib diisi sesuai KTP.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.unique' => 'Email ini sudah terdaftar dalam sistem.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'phone_number.regex' => 'Format nomor telepon/WhatsApp tidak valid (contoh: 08123456789).',
            'nik.required' => 'Nomor Induk Kependudukan (NIK) wajib diisi.',
            'nik.unique' => 'NIK ini sudah terdaftar dalam sistem.',
            'no_kk.required' => 'Nomor Kartu Keluarga (KK) wajib diisi.',
            'tempat_lahir.required' => 'Tempat lahir wajib diisi.',
            'tanggal_lahir.required' => 'Tanggal lahir wajib diisi.',
            'tanggal_lahir.before' => 'Tanggal lahir harus sebelum hari ini.',
            'jenis_kelamin.required' => 'Pilih jenis kelamin.',
            'alamat.required' => 'Alamat domisili lengkap wajib diisi.',
            'desa.required' => 'Nama desa/kelurahan wajib diisi.',
            'kecamatan.required' => 'Nama kecamatan wajib diisi.',
            'kabupaten.required' => 'Nama kabupaten/kota wajib diisi.',
            'provinsi.required' => 'Nama provinsi wajib diisi.',
        ];
    }
}
