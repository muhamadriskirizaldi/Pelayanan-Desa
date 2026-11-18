<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case MASYARAKAT = 'masyarakat';
    case STAFF = 'staff';
    case SUPERVISOR = 'supervisor';
    case KEPALA_DESA = 'kepala_desa';

    /**
     * Label representasi peran dalam Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrator',
            self::MASYARAKAT => 'Masyarakat / Pemohon',
            self::STAFF => 'Staff Pelayanan Desa',
            self::SUPERVISOR => 'Supervisor Verifikasi',
            self::KEPALA_DESA => 'Kepala Desa',
        };
    }

    /**
     * Deskripsi tanggung jawab peran dalam alur pelayanan.
     */
    public function description(): string
    {
        return match ($this) {
            self::ADMIN => 'Pengelolaan data master, sistem, hak akses, dan audit trail.',
            self::MASYARAKAT => 'Mengajukan surat permohonan online dan melacak status.',
            self::STAFF => 'Pemeriksaan kelengkapan administrasi dan validasi dokumen.',
            self::SUPERVISOR => 'Verifikasi faktual dan pengujian kelayakan permohonan.',
            self::KEPALA_DESA => 'Persetujuan akhir (approval) dan penandatanganan dokumen.',
        };
    }

    /**
     * Daftar semua nilai enum.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
