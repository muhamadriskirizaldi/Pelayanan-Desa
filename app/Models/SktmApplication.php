<?php

namespace App\Models;

use App\Enums\AiCheckStatus;
use App\Enums\SktmStatus;
use App\Enums\UserRole;
use App\Services\Ai\Data\AiCheckResult;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SktmApplication extends Model
{
    use HasFactory, HasUuids;

    /**
     * Tipe primary key (UUID non-incrementing).
     *
     * @var string
     */
    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sktm_applications';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nomor_pengajuan',
        'nomor_surat',
        'file_surat_path',
        'file_surat_name',
        'file_surat_size',
        'file_surat_generated_at',
        'file_surat_hash',
        'user_id',
        'nama_pemohon',
        'nik',
        'no_kk',
        'alamat',
        'rt',
        'rw',
        'desa',
        'kecamatan',
        'kabupaten',
        'provinsi',
        'keperluan',
        'pekerjaan',
        'penghasilan_bulanan',
        'jumlah_tanggungan',
        'kondisi_ekonomi',
        'dokumen_ktp_path',
        'dokumen_ktp_original_name',
        'dokumen_ktp_size',
        'dokumen_kk_path',
        'dokumen_kk_original_name',
        'dokumen_kk_size',
        'dokumen_pendukung_path',
        'dokumen_pendukung_original_name',
        'dokumen_pendukung_size',
        'status',
        'is_ready_for_issuance',
        'tanggal_pengajuan',
        'tanggal_disetujui',
        'catatan',
        'ai_check_status',
        'ai_check_result',
        'ai_checked_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SktmStatus::class,
            'ai_check_status' => AiCheckStatus::class,
            'ai_check_result' => 'array',
            'ai_checked_at' => 'datetime',
            'tanggal_pengajuan' => 'datetime',
            'tanggal_disetujui' => 'datetime',
            'file_surat_generated_at' => 'datetime',
            'file_surat_size' => 'integer',
            'is_ready_for_issuance' => 'boolean',
            'penghasilan_bulanan' => 'integer',
            'jumlah_tanggungan' => 'integer',
        ];
    }

    /**
     * Relasi ke akun pengguna pemohon (Warga).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Generator nomor pengajuan otomatis unik format: SKTM-YYYYMMDD-XXXX
     */
    public static function generateNomorPengajuan(): string
    {
        $datePrefix = date('Ymd');
        $prefix = "SKTM-{$datePrefix}-";

        $latest = self::where('nomor_pengajuan', 'LIKE', "{$prefix}%")
            ->orderBy('nomor_pengajuan', 'desc')
            ->first();

        if ($latest) {
            $lastNumber = (int) substr($latest->nomor_pengajuan, -4);
            $nextNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $nextNumber = '0001';
        }

        return $prefix.$nextNumber;
    }

    /**
     * Generator nomor surat resmi SKTM format: 470/XXXX/SKTM/ROMAN_MONTH/YEAR
     * Contoh: 470/0001/SKTM/XI/2026
     */
    public static function generateNomorSurat(): string
    {
        $year = date('Y');
        $month = (int) date('n');
        $romanMonths = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
        ];
        $romanMonth = $romanMonths[$month] ?? 'I';

        $kodeSurat = config('village.sktm.kode_surat', '470');
        $prefix = "{$kodeSurat}/";
        $suffix = "/SKTM/{$romanMonth}/{$year}";

        $countThisYear = self::whereNotNull('nomor_surat')
            ->where(function ($q) use ($year) {
                $q->whereYear('tanggal_disetujui', $year)
                    ->orWhere('nomor_surat', 'like', "%/{$year}");
            })
            ->count();

        $number = $countThisYear + 1;
        do {
            $formattedNumber = str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            $candidate = "{$prefix}{$formattedNumber}{$suffix}";
            $number++;
        } while (self::where('nomor_surat', $candidate)->exists());

        return $candidate;
    }

    /**
     * Relasi riwayat audit perubahan status pengajuan.
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(SktmStatusHistory::class, 'sktm_application_id')->latest();
    }

    /**
     * Periksa apakah pengajuan berstatus draf.
     */
    public function isDraft(): bool
    {
        return $this->status === SktmStatus::DRAFT;
    }

    /**
     * Periksa apakah pengajuan telah terkirim resmi.
     */
    public function isSubmitted(): bool
    {
        return $this->status === SktmStatus::SUBMITTED;
    }

    /**
     * Periksa apakah pengajuan sedang dalam proses pemeriksaan oleh Staff.
     */
    public function isUnderReview(): bool
    {
        return $this->status === SktmStatus::UNDER_REVIEW || $this->status === SktmStatus::STAFF_REVIEW;
    }

    /**
     * Periksa apakah pengajuan membutuhkan perbaikan dokumen/data dari pemohon.
     */
    public function isNeedRevision(): bool
    {
        return $this->status === SktmStatus::NEED_REVISION;
    }

    /**
     * Periksa apakah pengajuan telah diverifikasi oleh Staff dan diteruskan ke Supervisor.
     */
    public function isVerifiedByStaff(): bool
    {
        return $this->status === SktmStatus::VERIFIED_BY_STAFF;
    }

    /**
     * Periksa apakah pengajuan telah diverifikasi kelayakannya oleh Supervisor dan diteruskan ke Kades.
     */
    public function isVerifiedBySupervisor(): bool
    {
        return $this->status === SktmStatus::VERIFIED_BY_SUPERVISOR || $this->status === SktmStatus::VERIFIED_SUPERVISOR;
    }

    /**
     * Periksa apakah pengajuan ditolak.
     */
    public function isRejected(): bool
    {
        return $this->status === SktmStatus::REJECTED;
    }

    /**
     * Periksa apakah pengajuan telah disetujui akhir oleh Kepala Desa.
     */
    public function isApproved(): bool
    {
        return $this->status === SktmStatus::APPROVED || $this->status === SktmStatus::APPROVED_KADES;
    }

    /**
     * Periksa apakah pengajuan telah disetujui akhir oleh Kepala Desa (Alias).
     */
    public function isApprovedKades(): bool
    {
        return $this->isApproved();
    }

    /**
     * Periksa apakah pengajuan siap diterbitkan surat resminya.
     */
    public function isReadyForIssuance(): bool
    {
        return (bool) ($this->is_ready_for_issuance || $this->isApproved());
    }

    /**
     * Ambil riwayat audit persetujuan terakhir dari Kepala Desa.
     */
    public function latestKadesApproval(): ?SktmStatusHistory
    {
        return $this->statusHistories()
            ->where('role', UserRole::KEPALA_DESA->value)
            ->latest()
            ->first();
    }

    /**
     * Ambil riwayat audit verifikasi terakhir dari Staff.
     */
    public function latestStaffVerification(): ?SktmStatusHistory
    {
        return $this->statusHistories()
            ->where('to_status', SktmStatus::VERIFIED_BY_STAFF->value)
            ->latest()
            ->first();
    }

    /**
     * Ambil riwayat audit verifikasi terakhir dari Supervisor.
     */
    public function latestSupervisorVerification(): ?SktmStatusHistory
    {
        return $this->statusHistories()
            ->where('role', UserRole::SUPERVISOR->value)
            ->latest()
            ->first();
    }

    /**
     * Apakah pengajuan dapat diubah oleh pemohon (Draf atau Memerlukan Perbaikan).
     */
    public function canBeEditedBy(User $user): bool
    {
        return $this->user_id === $user->id && ($this->isDraft() || $this->isNeedRevision());
    }

    /**
     * Mencatat transisi status ke dalam audit/history log.
     */
    public function recordStatusTransition(
        User $user,
        SktmStatus|string $newStatus,
        ?string $notes = null
    ): SktmStatusHistory {
        $previousStatus = $this->status;

        $newStatusEnum = $newStatus instanceof SktmStatus
            ? $newStatus
            : SktmStatus::from($newStatus);

        $this->status = $newStatusEnum;
        if ($notes !== null) {
            $this->catatan = $notes;
        }

        // Jika disetujui Kepala Desa, buat nomor surat otomatis dan tandai siap diterbitkan
        if ($newStatusEnum === SktmStatus::APPROVED || $newStatusEnum === SktmStatus::APPROVED_KADES) {
            if (! $this->nomor_surat) {
                $this->nomor_surat = self::generateNomorSurat();
            }
            $this->tanggal_disetujui = now();
            $this->is_ready_for_issuance = true;
        }

        $this->save();

        $roleValue = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
        $prevValue = $previousStatus instanceof SktmStatus ? $previousStatus->value : (string) $previousStatus;

        $history = $this->statusHistories()->create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'role' => $roleValue,
            'from_status' => $prevValue,
            'to_status' => $newStatusEnum->value,
            'catatan' => $notes,
        ]);

        // Otomatis kirim notifikasi ke pemohon sesuai jenis transisi
        $this->dispatchNotificationForStatus($newStatusEnum, $notes);

        return $history;
    }

    /**
     * Kirim notifikasi ke pemohon berdasarkan perubahan status.
     */
    protected function dispatchNotificationForStatus(SktmStatus $status, ?string $notes = null): void
    {
        /** @var NotificationService $notifier */
        $notifier = app(NotificationService::class);

        match ($status) {
            SktmStatus::SUBMITTED => $notifier->notifySubmitted($this),
            SktmStatus::NEED_REVISION => $notifier->notifyNeedRevision($this, $notes),
            SktmStatus::VERIFIED_BY_STAFF => $notifier->notifyVerifiedByStaff($this, $notes),
            SktmStatus::VERIFIED_BY_SUPERVISOR, SktmStatus::VERIFIED_SUPERVISOR => $notifier->notifyVerifiedBySupervisor($this, $notes),
            SktmStatus::APPROVED, SktmStatus::APPROVED_KADES => (function () use ($notifier) {
                $notifier->notifyApproved($this);
                $notifier->notifyDocumentReady($this);
            })(),
            SktmStatus::REJECTED => $notifier->notifyRejected($this, $notes),
            default => null,
        };
    }

    /**
     * Aksesor: Sensor data sensitif NIK.
     */
    protected function maskedNik(): Attribute
    {
        return Attribute::make(
            get: function () {
                $nik = $this->nik;
                if (! $nik || strlen($nik) < 10) {
                    return $nik;
                }

                return substr($nik, 0, 6).str_repeat('*', max(0, strlen($nik) - 10)).substr($nik, -4);
            }
        );
    }

    /**
     * Aksesor: Sensor data sensitif No KK.
     */
    protected function maskedNoKk(): Attribute
    {
        return Attribute::make(
            get: function () {
                $noKk = $this->no_kk;
                if (! $noKk || strlen($noKk) < 10) {
                    return $noKk;
                }

                return substr($noKk, 0, 6).str_repeat('*', max(0, strlen($noKk) - 10)).substr($noKk, -4);
            }
        );
    }

    /**
     * Aksesor: Format Rupiah untuk estimasi penghasilan.
     */
    protected function formattedPenghasilan(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->penghasilan_bulanan
                ? 'Rp '.number_format($this->penghasilan_bulanan, 0, ',', '.')
                : 'Tidak Berpenghasilan Tetap'
        );
    }

    /**
     * Aksesor: Format alamat lengkap.
     */
    protected function formattedAlamat(): Attribute
    {
        return Attribute::make(
            get: function () {
                $parts = [
                    $this->alamat,
                    ($this->rt ? 'RT '.$this->rt : null),
                    ($this->rw ? 'RW '.$this->rw : null),
                    ($this->desa ? 'Desa/Kel. '.$this->desa : null),
                    ($this->kecamatan ? 'Kec. '.$this->kecamatan : null),
                    ($this->kabupaten ? $this->kabupaten : null),
                    ($this->provinsi ? 'Prov. '.$this->provinsi : null),
                ];

                return implode(', ', array_filter($parts));
            }
        );
    }

    /**
     * Format ukuran file menjadi KB/MB yang dapat dibaca manusia.
     */
    public static function formatBytes(?int $bytes): string
    {
        if (! $bytes || $bytes <= 0) {
            return '-';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }

        return number_format($bytes / 1024, 1).' KB';
    }

    /**
     * Memeriksa apakah permohonan telah melalui proses AI Document Check.
     */
    public function hasAiCheck(): bool
    {
        return ! empty($this->ai_check_status) && ! empty($this->ai_check_result);
    }

    /**
     * Mengembalikan objek AiCheckResult dari data json tersimpan.
     */
    public function getAiCheckResult(): ?AiCheckResult
    {
        if (! $this->hasAiCheck() || ! is_array($this->ai_check_result)) {
            return null;
        }

        return AiCheckResult::fromArray($this->ai_check_result);
    }

    /**
     * Label ramah status AI Document Check.
     */
    public function aiCheckStatusLabel(): string
    {
        if ($this->ai_check_status instanceof AiCheckStatus) {
            return $this->ai_check_status->label();
        }

        if (is_string($this->ai_check_status)) {
            $enum = AiCheckStatus::tryFrom($this->ai_check_status);

            return $enum ? $enum->label() : $this->ai_check_status;
        }

        return 'Belum Diperiksa AI';
    }
}
