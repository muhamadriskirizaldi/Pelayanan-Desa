<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    use HasFactory, HasUuids;

    /**
     * Primary key UUID non-incrementing.
     *
     * @var string
     */
    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Nama tabel.
     *
     * @var string
     */
    protected $table = 'app_notifications';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'sktm_application_id',
        'type',
        'title',
        'message',
        'action_url',
        'is_read',
        'read_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    /**
     * Relasi ke penerima notifikasi (Pengguna).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relasi opsional ke berkas permohonan SKTM terkait.
     */
    public function sktmApplication(): BelongsTo
    {
        return $this->belongsTo(SktmApplication::class, 'sktm_application_id');
    }

    /**
     * Scope untuk notifikasi yang belum dibaca.
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Tandai notifikasi sebagai sudah dibaca.
     */
    public function markAsRead(): bool
    {
        if ($this->is_read) {
            return true;
        }

        $this->is_read = true;
        $this->read_at = now();

        return $this->save();
    }

    /**
     * Kembalikan kode warna/tipe indikator visual badge berdasarkan jenis notifikasi.
     */
    public function typeBadgeClass(): string
    {
        return match ($this->type) {
            'application_submitted' => 'badge-submitted',
            'need_revision' => 'badge-warning',
            'verified_by_staff' => 'badge-staff',
            'verified_by_supervisor' => 'badge-supervisor',
            'approved', 'document_ready' => 'badge-approved',
            'rejected' => 'badge-rejected',
            default => 'badge-staff',
        };
    }
}
