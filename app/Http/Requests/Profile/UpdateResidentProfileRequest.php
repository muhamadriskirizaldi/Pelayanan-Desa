<?php

namespace App\Http\Requests\Profile;

use App\Enums\Gender;
use App\Rules\ValidNik;
use App\Rules\ValidNoKk;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateResidentProfileRequest extends FormRequest
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
        $profile = $this->user()?->residentProfile;
        $profileId = $profile?->id;

        return [
            'nik' => [
                'required',
                'string',
                new ValidNik,
                Rule::unique('resident_profiles', 'nik')->ignore($profileId),
            ],
            'no_kk' => ['required', 'string', new ValidNoKk],
            'tempat_lahir' => ['required', 'string', 'max:100'],
            'tanggal_lahir' => ['required', 'date', 'before:today'],
            'jenis_kelamin' => ['required', Rule::enum(Gender::class)],
            'alamat' => ['required', 'string', 'max:500'],
            'rt' => ['nullable', 'string', 'max:5'],
            'rw' => ['nullable', 'string', 'max:5'],
            'desa' => ['required', 'string', 'max:100'],
            'kecamatan' => ['required', 'string', 'max:100'],
            'kabupaten' => ['required', 'string', 'max:100'],
            'provinsi' => ['required', 'string', 'max:100'],
            'kode_pos' => ['nullable', 'string', 'digits:5'],
            'agama' => ['nullable', 'string', 'max:30'],
            'pekerjaan' => ['nullable', 'string', 'max:100'],
            'status_perkawinan' => ['nullable', 'string', 'max:50'],
        ];
    }
}
