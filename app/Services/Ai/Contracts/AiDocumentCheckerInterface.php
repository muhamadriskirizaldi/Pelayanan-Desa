<?php

namespace App\Services\Ai\Contracts;

use App\Models\SktmApplication;
use App\Services\Ai\Data\AiCheckResult;

interface AiDocumentCheckerInterface
{
    /**
     * Memeriksa dan menganalisis berkas dokumen persyaratan SKTM (KTP & KK),
     * mengekstrak data identitas (Nama, NIK, No KK, Alamat, Tanggal Lahir),
     * dan membandingkannya dengan data formulir yang diisi pemohon.
     *
     * PERHATIAN:
     * Hasil pemeriksaan hanya berupa rekomendasi pemeriksaan untuk Staff.
     * Tidak boleh mengubah status permohonan secara otomatis atau mengambil
     * keputusan final atas kelayakan warga.
     *
     * @param  SktmApplication  $application  Permohonan SKTM yang akan diperiksa.
     * @param  array<string, mixed>  $options  Opsi tambahan pemeriksaan atau pengujian.
     * @return AiCheckResult Hasil pemeriksaan dan perbandingan data.
     */
    public function check(SktmApplication $application, array $options = []): AiCheckResult;
}
