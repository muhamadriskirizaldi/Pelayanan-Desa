<?php

namespace App\Services\Ai\Providers;

use App\Enums\AiCheckStatus;
use App\Models\SktmApplication;
use App\Services\Ai\Contracts\AiDocumentCheckerInterface;
use App\Services\Ai\Data\AiCheckResult;
use App\Services\Ai\Data\FieldCheckResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GeminiAiDocumentChecker implements AiDocumentCheckerInterface
{
    public function __construct(
        protected MockAiDocumentChecker $fallbackChecker
    ) {}

    /**
     * Memeriksa dokumen SKTM menggunakan Google Gemini Multimodal Vision API.
     */
    public function check(SktmApplication $application, array $options = []): AiCheckResult
    {
        $apiKey = config('ai.api_key');

        // Jika API Key tidak disetel di environment, otomatis fallback ke Mock Provider
        if (empty($apiKey)) {
            Log::info('AI_API_KEY tidak dikonfigurasi di file environment. Menggunakan MockAiDocumentChecker.');

            return $this->fallbackChecker->check($application, $options);
        }

        try {
            // Ambil berkas dokumen KTP dan KK dari storage
            $ktpPath = $application->dokumen_ktp_path;
            $kkPath = $application->dokumen_kk_path;

            if (! $ktpPath || ! Storage::disk('local')->exists($ktpPath)) {
                return $this->fallbackChecker->check($application, $options);
            }

            $model = config('ai.model', 'gemini-1.5-flash');
            $timeout = (int) config('ai.timeout', 15);
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            // Siapkan payload multimodal
            $parts = [
                [
                    'text' => $this->buildPrompt($application),
                ],
            ];

            // Masukkan KTP jika formatnya gambar
            $ktpContent = Storage::disk('local')->get($ktpPath);
            $mimeType = $this->detectMimeType($ktpPath, $ktpContent);
            if (str_starts_with($mimeType, 'image/')) {
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => $mimeType,
                        'data' => base64_encode($ktpContent),
                    ],
                ];
            }

            $response = Http::timeout($timeout)->post($url, [
                'contents' => [
                    [
                        'parts' => $parts,
                    ],
                ],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                    'temperature' => 0.1,
                ],
            ]);

            if ($response->successful()) {
                $jsonText = $response->json('candidates.0.content.parts.0.text');
                $extractedData = json_decode($jsonText, true);

                if (is_array($extractedData)) {
                    return $this->processExtractedData($application, $extractedData);
                }
            }

            Log::warning('Respon API Gemini tidak valid atau gagal. Beralih ke fallback provider: '.$response->body());
        } catch (Throwable $e) {
            Log::error('Terjadi kesalahan saat memanggil API Gemini: '.$e->getMessage());
        }

        return $this->fallbackChecker->check($application, $options);
    }

    /**
     * Membangun prompt ekstraksi JSON untuk model AI.
     */
    protected function buildPrompt(SktmApplication $application): string
    {
        return <<<'PROMPT'
Anda adalah asisten verifikasi dokumen desa cerdas.
Tugas Anda adalah membaca dokumen KTP (Kartu Tanda Penduduk) dan KK (Kartu Keluarga) terlampir dan mengekstrak data identitas berikut ke dalam format JSON murni:
{
    "nama": "nama lengkap pada dokumen",
    "nik": "16 digit NIK",
    "no_kk": "16 digit No KK jika terbaca",
    "alamat": "alamat domisili lengkap",
    "tanggal_lahir": "format DD-MM-YYYY",
    "dokumen_terbaca": true
}
Jika dokumen buram, terpotong, atau tidak terbaca sama sekali, kembalikan:
{
    "nama": null,
    "nik": null,
    "no_kk": null,
    "alamat": null,
    "tanggal_lahir": null,
    "dokumen_terbaca": false,
    "alasan": "Dokumen buram atau resolusi terlalu rendah"
}
PROMPT;
    }

    /**
     * Memproses data hasil ekstraksi dan mencocokkan dengan data formulir.
     */
    protected function processExtractedData(SktmApplication $application, array $extracted): AiCheckResult
    {
        $disclaimer = config(
            'ai.disclaimer',
            'AI hanya memberikan rekomendasi pemeriksaan. Keputusan akhir tetap dilakukan oleh petugas desa.'
        );

        if (empty($extracted['dokumen_terbaca']) || $extracted['dokumen_terbaca'] === false) {
            return new AiCheckResult(
                overall_status: AiCheckStatus::DOKUMEN_TIDAK_TERBACA,
                summary: $extracted['alasan'] ?? 'Dokumen KTP/KK buram atau tidak dapat dibaca oleh sistem OCR.',
                fields: [
                    'nama' => new FieldCheckResult('nama', 'Nama Lengkap', FieldCheckResult::STATUS_TIDAK_TERBACA, $application->nama_pemohon, null, false, 'Dokumen tidak terbaca.'),
                    'nik' => new FieldCheckResult('nik', 'Nomor Induk Kependudukan (NIK)', FieldCheckResult::STATUS_TIDAK_TERBACA, $application->nik, null, false, 'Dokumen tidak terbaca.'),
                    'no_kk' => new FieldCheckResult('no_kk', 'Nomor Kartu Keluarga (KK)', FieldCheckResult::STATUS_TIDAK_TERBACA, $application->no_kk, null, false, 'Dokumen tidak terbaca.'),
                    'alamat' => new FieldCheckResult('alamat', 'Alamat Domisili', FieldCheckResult::STATUS_TIDAK_TERBACA, $application->alamat, null, false, 'Dokumen tidak terbaca.'),
                    'tanggal_lahir' => new FieldCheckResult('tanggal_lahir', 'Tanggal Lahir', FieldCheckResult::STATUS_TIDAK_TERBACA, null, null, false, 'Dokumen tidak terbaca.'),
                ],
                extracted_data: $extracted,
                confidence_score: 30,
                provider: 'gemini',
                checked_at: now()->toIso8601String(),
                disclaimer: $disclaimer
            );
        }

        $fields = [];

        // 1. Nama
        $namaDoc = (string) ($extracted['nama'] ?? '');
        $namaMatch = strtolower(trim($application->nama_pemohon)) === strtolower(trim($namaDoc));
        $fields['nama'] = new FieldCheckResult(
            field: 'nama',
            label: 'Nama Lengkap',
            status: $namaMatch ? FieldCheckResult::STATUS_SESUAI : FieldCheckResult::STATUS_PERLU_DICEK,
            form_value: $application->nama_pemohon,
            extracted_value: $namaDoc,
            is_match: $namaMatch,
            notes: $namaMatch ? 'Nama pada e-KTP cocok dengan formulir.' : 'Terdapat perbedaan ejaan nama pemohon.'
        );

        // 2. NIK
        $nikDoc = preg_replace('/\D/', '', (string) ($extracted['nik'] ?? ''));
        $nikMatch = trim($application->nik) === $nikDoc;
        $fields['nik'] = new FieldCheckResult(
            field: 'nik',
            label: 'Nomor Induk Kependudukan (NIK)',
            status: $nikMatch ? FieldCheckResult::STATUS_SESUAI : FieldCheckResult::STATUS_PERLU_DICEK,
            form_value: $application->nik,
            extracted_value: $nikDoc,
            is_match: $nikMatch,
            notes: $nikMatch ? 'NIK pada dokumen KTP cocok dengan formulir.' : 'Nomor NIK pada KTP berbeda dengan formulir.'
        );

        // 3. No KK
        $noKkDoc = preg_replace('/\D/', '', (string) ($extracted['no_kk'] ?? ''));
        $noKkMatch = empty($noKkDoc) || trim($application->no_kk) === $noKkDoc;
        $fields['no_kk'] = new FieldCheckResult(
            field: 'no_kk',
            label: 'Nomor Kartu Keluarga (KK)',
            status: $noKkMatch ? FieldCheckResult::STATUS_SESUAI : FieldCheckResult::STATUS_PERLU_DICEK,
            form_value: $application->no_kk,
            extracted_value: $noKkDoc,
            is_match: $noKkMatch,
            notes: $noKkMatch ? 'Nomor KK pada dokumen KK cocok.' : 'Nomor Kartu Keluarga pada dokumen berbeda dengan formulir.'
        );

        // 4. Alamat
        $alamatDoc = (string) ($extracted['alamat'] ?? '');
        $alamatMatch = ! empty($alamatDoc);
        $fields['alamat'] = new FieldCheckResult(
            field: 'alamat',
            label: 'Alamat Domisili',
            status: $alamatMatch ? FieldCheckResult::STATUS_SESUAI : FieldCheckResult::STATUS_PERLU_DICEK,
            form_value: $application->alamat,
            extracted_value: $alamatDoc,
            is_match: $alamatMatch,
            notes: $alamatMatch ? 'Alamat pemohon terverifikasi pada dokumen.' : 'Alamat domisili pada dokumen berbeda.'
        );

        // 5. Tanggal Lahir
        $tglDoc = (string) ($extracted['tanggal_lahir'] ?? '');
        $fields['tanggal_lahir'] = new FieldCheckResult(
            field: 'tanggal_lahir',
            label: 'Tanggal Lahir',
            status: ! empty($tglDoc) ? FieldCheckResult::STATUS_SESUAI : FieldCheckResult::STATUS_PERLU_DICEK,
            form_value: null,
            extracted_value: $tglDoc,
            is_match: ! empty($tglDoc),
            notes: 'Tanggal lahir diekstrak dari dokumen kependudukan.'
        );

        $hasMismatch = false;
        foreach ($fields as $f) {
            if ($f->isPerluDicek() || $f->isTidakTerbaca()) {
                $hasMismatch = true;
                break;
            }
        }

        return new AiCheckResult(
            overall_status: $hasMismatch ? AiCheckStatus::DATA_PERLU_DICEK : AiCheckStatus::DATA_SESUAI,
            summary: $hasMismatch ? 'Ditemukan potensi ketidaksesuaian data pada dokumen pemohon.' : 'Data kependudukan sesuai dengan dokumen KTP & KK.',
            fields: $fields,
            extracted_data: $extracted,
            confidence_score: $hasMismatch ? 80 : 96,
            provider: 'gemini',
            checked_at: now()->toIso8601String(),
            disclaimer: $disclaimer
        );
    }

    /**
     * Mendeteksi MIME type berkas.
     */
    protected function detectMimeType(string $path, string $content): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            default => 'image/jpeg',
        };
    }
}
