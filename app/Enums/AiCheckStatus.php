<?php

namespace App\Enums;

enum AiCheckStatus: string
{
    case DATA_SESUAI = 'DATA_SESUAI';
    case DATA_PERLU_DICEK = 'DATA_PERLU_DICEK';
    case DOKUMEN_TIDAK_TERBACA = 'DOKUMEN_TIDAK_TERBACA';

    /**
     * Label representasi ramah pengguna dalam Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::DATA_SESUAI => 'Data Sesuai',
            self::DATA_PERLU_DICEK => 'Data Perlu Dicek',
            self::DOKUMEN_TIDAK_TERBACA => 'Dokumen Tidak Terbaca',
        };
    }

    /**
     * Deskripsi ringkas untuk masing-masing status hasil pemeriksaan AI.
     */
    public function description(): string
    {
        return match ($this) {
            self::DATA_SESUAI => 'Seluruh data kependudukan pada formulir cocok dengan hasil pembacaan OCR dokumen KTP & KK.',
            self::DATA_PERLU_DICEK => 'Ditemukan potensi ketidaksesuaian antara data formulir dengan dokumen pemohon. Diperlukan ketelitian petugas.',
            self::DOKUMEN_TIDAK_TERBACA => 'Dokumen fisik KTP atau Kartu Keluarga buram, terpotong, atau tidak dapat diuraikan oleh sistem OCR.',
        };
    }

    /**
     * Kelas badge warna untuk antarmuka pengguna.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::DATA_SESUAI => 'badge-ai-success',
            self::DATA_PERLU_DICEK => 'badge-ai-warning',
            self::DOKUMEN_TIDAK_TERBACA => 'badge-ai-danger',
        };
    }

    /**
     * Ikon status.
     */
    public function icon(): string
    {
        return match ($this) {
            self::DATA_SESUAI => '✓',
            self::DATA_PERLU_DICEK => '⚠',
            self::DOKUMEN_TIDAK_TERBACA => '✖',
        };
    }
}
