<?php

namespace App\Services\Ai;

use App\Models\SktmApplication;
use App\Services\Ai\Contracts\AiDocumentCheckerInterface;
use App\Services\Ai\Data\AiCheckResult;
use App\Services\Ai\Providers\GeminiAiDocumentChecker;
use App\Services\Ai\Providers\MockAiDocumentChecker;
use Illuminate\Contracts\Container\Container;

class AiDocumentService
{
    public function __construct(
        protected Container $container,
        protected MockAiDocumentChecker $mockChecker
    ) {}

    /**
     * Memeriksa dokumen SKTM menggunakan provider AI yang dikonfigurasi.
     * Hasil pemeriksaan disimpan ke database sebagai metadata rekomendasi untuk Staff.
     *
     * PENTING:
     * Metode ini TIDAK mengubah status alur kerja pengajuan ($sktm->status)
     * dan TIDAK mengambil keputusan otomatis! Keputusan tetap di tangan Staff.
     */
    public function checkApplication(SktmApplication $sktm, bool $force = false, array $options = []): AiCheckResult
    {
        // Gunakan hasil tersimpan jika sudah ada dan tidak dipaksa re-scan
        if (! $force && ! empty($sktm->ai_check_result) && is_array($sktm->ai_check_result)) {
            return AiCheckResult::fromArray($sktm->ai_check_result);
        }

        $checker = $this->getChecker();
        $result = $checker->check($sktm, $options);

        // Simpan hasil pemeriksaan AI ke dalam kolom permohonan SKTM
        $sktm->update([
            'ai_check_status' => $result->overall_status->value,
            'ai_check_result' => $result->toArray(),
            'ai_checked_at' => now(),
        ]);

        return $result;
    }

    /**
     * Mendapatkan instansi checker yang sedang aktif sesuai konfigurasi environment.
     */
    public function getChecker(): AiDocumentCheckerInterface
    {
        $driver = config('ai.driver', 'mock');

        return match ($driver) {
            'mock' => $this->mockChecker,
            'gemini' => $this->container->make(GeminiAiDocumentChecker::class),
            default => $this->mockChecker,
        };
    }
}
