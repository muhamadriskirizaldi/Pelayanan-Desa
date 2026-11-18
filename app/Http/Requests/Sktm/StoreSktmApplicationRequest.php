<?php

namespace App\Http\Requests\Sktm;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSktmApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isMasyarakat();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isSubmitting = $this->input('action') === 'submit';
        $application = $this->route('sktm'); // Jika sedang mengupdate draft

        $hasExistingKtp = $application && $application->dokumen_ktp_path;
        $hasExistingKk = $application && $application->dokumen_kk_path;

        // Aturan validasi file upload aman (non-executable, magic bytes inspection, max 3MB, PDF/JPG/PNG)
        $fileRules = ['file', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:3072'];

        $rules = [
            'action' => ['required', Rule::in(['draft', 'submit'])],
            'keperluan' => [$isSubmitting ? 'required' : 'required', 'string', 'min:3', 'max:255'],
            'pekerjaan' => ['nullable', 'string', 'max:100'],
            'penghasilan_bulanan' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'jumlah_tanggungan' => ['nullable', 'integer', 'min:1', 'max:30'],
            'kondisi_ekonomi' => [$isSubmitting ? 'required' : 'nullable', 'string', $isSubmitting ? 'min:10' : 'nullable', 'max:2000'],

            'alamat' => [$isSubmitting ? 'required' : 'nullable', 'string', 'max:500'],
            'rt' => ['nullable', 'string', 'max:5'],
            'rw' => ['nullable', 'string', 'max:5'],
            'desa' => ['nullable', 'string', 'max:100'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'kabupaten' => ['nullable', 'string', 'max:100'],
            'provinsi' => ['nullable', 'string', 'max:100'],

            // Validasi Dokumen Persyaratan
            'dokumen_ktp' => array_merge(
                $isSubmitting && ! $hasExistingKtp ? ['required'] : ['nullable'],
                $fileRules
            ),
            'dokumen_kk' => array_merge(
                $isSubmitting && ! $hasExistingKk ? ['required'] : ['nullable'],
                $fileRules
            ),
            'dokumen_pendukung' => array_merge(
                ['nullable'],
                $fileRules
            ),
            'catatan' => ['nullable', 'string', 'max:1000'],
        ];

        return $rules;
    }

    /**
     * Pesan kesalahan khusus dalam Bahasa Indonesia.
     */
    public function messages(): array
    {
        return [
            'keperluan.required' => 'Keperluan permohonan SKTM wajib dipilih atau diisi.',
            'keperluan.min' => 'Keperluan permohonan minimal 3 karakter.',
            'kondisi_ekonomi.required' => 'Keterangan kondisi ekonomi dan tempat tinggal wajib diisi saat mengajukan permohonan.',
            'kondisi_ekonomi.min' => 'Keterangan kondisi ekonomi minimal 10 karakter.',
            'dokumen_ktp.required' => 'Dokumen Foto/Scan e-KTP wajib diunggah untuk pengajuan resmi.',
            'dokumen_ktp.mimes' => 'Format file KTP harus berupa PDF, JPG, JPEG, atau PNG.',
            'dokumen_ktp.max' => 'Ukuran file KTP maksimal 3 MB.',
            'dokumen_kk.required' => 'Dokumen Foto/Scan Kartu Keluarga (KK) wajib diunggah untuk pengajuan resmi.',
            'dokumen_kk.mimes' => 'Format file Kartu Keluarga harus berupa PDF, JPG, JPEG, atau PNG.',
            'dokumen_kk.max' => 'Ukuran file Kartu Keluarga maksimal 3 MB.',
            'dokumen_pendukung.mimes' => 'Format dokumen pendukung harus berupa PDF, JPG, JPEG, atau PNG.',
            'dokumen_pendukung.max' => 'Ukuran file dokumen pendukung maksimal 3 MB.',
            'alamat.required' => 'Alamat domisili pemohon wajib diisi.',
            'penghasilan_bulanan.numeric' => 'Estimasi penghasilan harus berupa angka nominal.',
        ];
    }
}
