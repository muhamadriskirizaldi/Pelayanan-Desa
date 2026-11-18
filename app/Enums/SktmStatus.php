<?php

namespace App\Enums;

enum SktmStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case UNDER_REVIEW = 'under_review';
    case NEED_REVISION = 'need_revision';
    case VERIFIED_BY_STAFF = 'verified_by_staff';
    case VERIFIED_BY_SUPERVISOR = 'verified_by_supervisor';
    case STAFF_REVIEW = 'staff_review'; // Backward compatibility
    case VERIFIED_SUPERVISOR = 'verified_supervisor'; // Backward compatibility
    case APPROVED = 'approved';
    case APPROVED_KADES = 'approved_kades'; // Backward compatibility
    case REJECTED = 'rejected';

    /**
     * Label representasi status dalam Bahasa Indonesia yang ramah pengguna.
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draf (Belum Dikirim)',
            self::SUBMITTED => 'Terkirim (Menunggu Petugas)',
            self::UNDER_REVIEW, self::STAFF_REVIEW => 'Sedang Diperiksa Staff',
            self::NEED_REVISION => 'Perlu Perbaikan',
            self::VERIFIED_BY_STAFF => 'Diverifikasi Staff (Menuju Supervisor)',
            self::VERIFIED_BY_SUPERVISOR, self::VERIFIED_SUPERVISOR => 'Diverifikasi Supervisor (Menuju Kepala Desa)',
            self::APPROVED, self::APPROVED_KADES => 'Disetujui (Surat Terbit)',
            self::REJECTED => 'Permohonan Ditolak',
        };
    }

    /**
     * Class CSS badge untuk indikator visual status.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT => 'badge-draft',
            self::SUBMITTED => 'badge-submitted',
            self::UNDER_REVIEW, self::STAFF_REVIEW => 'badge-staff',
            self::NEED_REVISION => 'badge-warning',
            self::VERIFIED_BY_STAFF => 'badge-approved',
            self::VERIFIED_BY_SUPERVISOR, self::VERIFIED_SUPERVISOR => 'badge-supervisor',
            self::APPROVED, self::APPROVED_KADES => 'badge-approved',
            self::REJECTED => 'badge-rejected',
        };
    }

    /**
     * Render elemen HTML badge status.
     */
    public function badgeHtml(): string
    {
        return sprintf(
            '<span class="badge %s">%s</span>',
            htmlspecialchars($this->badgeClass(), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($this->label(), ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Menentukan apakah dokumen masih dapat diedit oleh pemohon.
     */
    public function canBeEdited(): bool
    {
        return $this === self::DRAFT || $this === self::NEED_REVISION;
    }

    /**
     * Menentukan apakah pengajuan telah resmi masuk ke antrian pelayanan desa.
     */
    public function isSubmitted(): bool
    {
        return $this !== self::DRAFT;
    }

    /**
     * Seluruh nilai enum.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
