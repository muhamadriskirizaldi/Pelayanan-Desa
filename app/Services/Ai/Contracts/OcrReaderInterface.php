<?php

namespace App\Services\Ai\Contracts;

interface OcrReaderInterface
{
    /**
     * Membaca dan mengekstrak teks mentah dari berkas gambar atau PDF.
     *
     * @param  string  $filePath  Jalur absolut berkas dokumen pada sistem penyimpanan.
     * @return string|null Teks hasil ekstraksi, atau null jika dokumen tidak terbaca.
     */
    public function extractText(string $filePath): ?string;
}
