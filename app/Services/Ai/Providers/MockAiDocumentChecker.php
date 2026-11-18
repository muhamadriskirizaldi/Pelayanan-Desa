<?php

namespace App\Services\Ai\Providers;

use App\Enums\AiCheckStatus;
use App\Models\SktmApplication;
use App\Services\Ai\Contracts\AiDocumentCheckerInterface;
use App\Services\Ai\Data\AiCheckResult;
use App\Services\Ai\Data\FieldCheckResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class MockAiDocumentChecker implements AiDocumentCheckerInterface
{
    /**
     * Memeriksa dokumen SKTM menggunakan Provider Mock Cerdas.
     */
    public function check(SktmApplication $application, array $options = []): AiCheckResult
    {
        $disclaimer = config(
            'ai.disclaimer',
            'AI hanya memberikan rekomendasi pemeriksaan. Keputusan akhir tetap dilakukan oleh petugas desa.'
        );

        // Opsi pengujian untuk simulasi dokumen tidak terbaca
        $simulateUnreadable = $options['simulate_unreadable'] ?? false;
        if (! $simulateUnreadable && ! empty($application->catatan) && str_contains($application->catatan, '[mock_unreadable]')) {
            $simulateUnreadable = true;
        }

        // Periksa keberadaan berkas fisik KTP dan KK (kecuali jika mode bypass storage diaktifkan)
        $hasKtpFile = ! empty($application->dokumen_ktp_path);
        $hasKkFile = ! empty($application->dokumen_kk_path);

        // Jika dokumen benar-benar belum diunggah atau disimulasikan unreadable
        if ($simulateUnreadable || (! $hasKtpFile && ! $hasKkFile)) {
            return $this->buildUnreadableResult($application, $disclaimer, 'Berkas KTP dan Kartu Keluarga belum diunggah atau tidak dapat dibaca.');
        }

        // Tentukan nilai simulasi perbaikan/mismatch untuk pengujian
        $forceMismatches = (array) ($options['force_mismatch'] ?? []);
        if (! empty($application->catatan)) {
            if (str_contains($application->catatan, '[mock_mismatch:no_kk]')) {
                $forceMismatches[] = 'no_kk';
            }
            if (str_contains($application->catatan, '[mock_mismatch:nik]')) {
                $forceMismatches[] = 'nik';
            }
            if (str_contains($application->catatan, '[mock_mismatch:nama]')) {
                $forceMismatches[] = 'nama';
            }
            if (str_contains($application->catatan, '[mock_mismatch:alamat]')) {
                $forceMismatches[] = 'alamat';
            }
            if (str_contains($application->catatan, '[mock_mismatch:tanggal_lahir]')) {
                $forceMismatches[] = 'tanggal_lahir';
            }
        }

        // Ambil data tanggal lahir dari Profil Kependudukan pemohon atau estimasi dari NIK
        $formTanggalLahir = $this->resolveFormBirthDate($application);

        // Ekstraksi OCR simulasi dari KTP & KK
        $extractedNama = in_array('nama', $forceMismatches, true)
            ? $application->nama_pemohon.' (Beda Ejaan)'
            : $application->nama_pemohon;

        $extractedNik = in_array('nik', $forceMismatches, true)
            ? substr($application->nik, 0, 15).'9'
            : $application->nik;

        $extractedNoKk = in_array('no_kk', $forceMismatches, true)
            ? substr($application->no_kk, 0, 15).((substr($application->no_kk, -1) === '1') ? '2' : '1')
            : $application->no_kk;

        $extractedAlamat = in_array('alamat', $forceMismatches, true)
            ? 'Kp. Durian Runtuh RT 01 / RW 01 (Berbeda dengan formulir)'
            : $this->buildStandardAddress($application);

        $extractedTanggalLahir = in_array('tanggal_lahir', $forceMismatches, true)
            ? '01-01-1980'
            : $formTanggalLahir;

        // Bandingkan setiap field (5 data identitas: Nama, NIK, No KK, Alamat, Tanggal Lahir)
        $fields = [];

        // 1. Nama Pemohon
        $namaMatch = ! in_array('nama', $forceMismatches, true)
            && strtolower(trim($application->nama_pemohon)) === strtolower(trim($extractedNama));
        $fields['nama'] = new FieldCheckResult(
            field: 'nama',
            label: 'Nama Lengkap',
            status: $namaMatch ? FieldCheckResult::STATUS_SESUAI : FieldCheckResult::STATUS_PERLU_DICEK,
            form_value: $application->nama_pemohon,
            extracted_value: $extractedNama,
            is_match: $namaMatch,
            notes: $namaMatch ? 'Nama pada e-KTP identik dengan formulir.' : 'Terdapat perbedaan ejaan nama antara e-KTP dan formulir.'
        );

        // 2. NIK (16 digit)
        $nikMatch = ! in_array('nik', $forceMismatches, true)
            && trim($application->nik) === trim($extractedNik);
        $fields['nik'] = new FieldCheckResult(
            field: 'nik',
            label: 'Nomor Induk Kependudukan (NIK)',
            status: $nikMatch ? FieldCheckResult::STATUS_SESUAI : FieldCheckResult::STATUS_PERLU_DICEK,
            form_value: $application->nik,
            extracted_value: $extractedNik,
            is_match: $nikMatch,
            notes: $nikMatch ? 'NIK 16 digit pada e-KTP valid dan cocok.' : 'Nomor NIK pada e-KTP berbeda dengan nomor yang diisi di formulir.'
        );

        // 3. Nomor KK (16 digit)
        $noKkMatch = ! in_array('no_kk', $forceMismatches, true)
            && trim($application->no_kk) === trim($extractedNoKk);
        $fields['no_kk'] = new FieldCheckResult(
            field: 'no_kk',
            label: 'Nomor Kartu Keluarga (KK)',
            status: $noKkMatch ? FieldCheckResult::STATUS_SESUAI : FieldCheckResult::STATUS_PERLU_DICEK,
            form_value: $application->no_kk,
            extracted_value: $extractedNoKk,
            is_match: $noKkMatch,
            notes: $noKkMatch ? 'Nomor KK pada dokumen Kartu Keluarga cocok.' : 'Nomor KK pada dokumen fisik berbeda dengan isian formulir.'
        );

        // 4. Alamat Domisili
        $alamatMatch = ! in_array('alamat', $forceMismatches, true);
        $fields['alamat'] = new FieldCheckResult(
            field: 'alamat',
            label: 'Alamat Domisili',
            status: $alamatMatch ? FieldCheckResult::STATUS_SESUAI : FieldCheckResult::STATUS_PERLU_DICEK,
            form_value: $application->alamat,
            extracted_value: $extractedAlamat,
            is_match: $alamatMatch,
            notes: $alamatMatch ? 'Alamat domisili terverifikasi pada dokumen.' : 'Alamat pada berkas KTP/KK berbeda dengan alamat pada formulir.'
        );

        // 5. Tanggal Lahir
        $tanggalLahirMatch = ! in_array('tanggal_lahir', $forceMismatches, true);
        $fields['tanggal_lahir'] = new FieldCheckResult(
            field: 'tanggal_lahir',
            label: 'Tanggal Lahir',
            status: $tanggalLahirMatch ? FieldCheckResult::STATUS_SESUAI : FieldCheckResult::STATUS_PERLU_DICEK,
            form_value: $formTanggalLahir,
            extracted_value: $extractedTanggalLahir,
            is_match: $tanggalLahirMatch,
            notes: $tanggalLahirMatch ? 'Tanggal lahir sesuai dengan data KTP/KK.' : 'Tanggal lahir pada dokumen tidak cocok dengan profil pemohon.'
        );

        // Tentukan Status Keseluruhan
        $hasMismatch = ! $namaMatch || ! $nikMatch || ! $noKkMatch || ! $alamatMatch || ! $tanggalLahirMatch;
        $overallStatus = $hasMismatch ? AiCheckStatus::DATA_PERLU_DICEK : AiCheckStatus::DATA_SESUAI;

        $summary = $hasMismatch
            ? 'Ditemukan ketidaksesuaian pada beberapa data kependudukan pemohon. Staff disarankan memeriksa fisik berkas secara manual.'
            : 'Seluruh data kependudukan (Nama, NIK, No KK, Alamat, Tanggal Lahir) pada dokumen fisik sesuai dengan isian formulir.';

        return new AiCheckResult(
            overall_status: $overallStatus,
            summary: $summary,
            fields: $fields,
            extracted_data: [
                'nama' => $extractedNama,
                'nik' => $extractedNik,
                'no_kk' => $extractedNoKk,
                'alamat' => $extractedAlamat,
                'tanggal_lahir' => $extractedTanggalLahir,
            ],
            confidence_score: $hasMismatch ? 78 : 98,
            provider: 'mock',
            checked_at: now()->toIso8601String(),
            disclaimer: $disclaimer
        );
    }

    /**
     * Membentuk hasil saat dokumen fisik tidak terbaca atau belum lengkap.
     */
    protected function buildUnreadableResult(SktmApplication $application, string $disclaimer, string $reason): AiCheckResult
    {
        $fields = [
            'nama' => new FieldCheckResult('nama', 'Nama Lengkap', FieldCheckResult::STATUS_TIDAK_TERBACA, $application->nama_pemohon, null, false, 'Dokumen KTP tidak terbaca.'),
            'nik' => new FieldCheckResult('nik', 'Nomor Induk Kependudukan (NIK)', FieldCheckResult::STATUS_TIDAK_TERBACA, $application->nik, null, false, 'Dokumen KTP tidak terbaca.'),
            'no_kk' => new FieldCheckResult('no_kk', 'Nomor Kartu Keluarga (KK)', FieldCheckResult::STATUS_TIDAK_TERBACA, $application->no_kk, null, false, 'Dokumen KK tidak terbaca.'),
            'alamat' => new FieldCheckResult('alamat', 'Alamat Domisili', FieldCheckResult::STATUS_TIDAK_TERBACA, $application->alamat, null, false, 'Dokumen tidak terbaca.'),
            'tanggal_lahir' => new FieldCheckResult('tanggal_lahir', 'Tanggal Lahir', FieldCheckResult::STATUS_TIDAK_TERBACA, $this->resolveFormBirthDate($application), null, false, 'Dokumen tidak terbaca.'),
        ];

        return new AiCheckResult(
            overall_status: AiCheckStatus::DOKUMEN_TIDAK_TERBACA,
            summary: $reason,
            fields: $fields,
            extracted_data: [],
            confidence_score: 20,
            provider: 'mock',
            checked_at: now()->toIso8601String(),
            disclaimer: $disclaimer
        );
    }

    /**
     * Mengambil tanggal lahir pemohon dari profil atau NIK.
     */
    protected function resolveFormBirthDate(SktmApplication $application): string
    {
        $profile = $application->user?->residentProfile;
        if ($profile && $profile->tanggal_lahir) {
            return Carbon::parse($profile->tanggal_lahir)->format('d-m-Y');
        }

        // Estimasi tanggal lahir dari NIK jika profil belum terhubung (format Indonesia: PPKKCC-DDMMYY-XXXX)
        $nik = preg_replace('/\D/', '', (string) $application->nik);
        if (strlen($nik) >= 12) {
            $rawDay = (int) substr($nik, 6, 2);
            $day = $rawDay > 40 ? $rawDay - 40 : $rawDay; // Wanita +40
            $month = (int) substr($nik, 8, 2);
            $yearShort = (int) substr($nik, 10, 2);
            $year = $yearShort > 40 ? 1900 + $yearShort : 2000 + $yearShort;

            if (checkdate($month, $day, $year)) {
                return sprintf('%02d-%02d-%04d', $day, $month, $year);
            }
        }

        return '15-05-1990';
    }

    /**
     * Membentuk alamat lengkap standar dari aplikasi SKTM.
     */
    protected function buildStandardAddress(SktmApplication $application): string
    {
        $parts = array_filter([
            $application->alamat,
            ($application->rt || $application->rw) ? "RT {$application->rt} / RW {$application->rw}" : null,
            $application->desa,
            $application->kecamatan,
        ]);

        return implode(', ', $parts);
    }
}
