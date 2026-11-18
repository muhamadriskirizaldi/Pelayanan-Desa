<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppNotificationLog extends Model
{
    protected $table = 'whatsapp_notification_logs';

    protected $fillable = [
        'user_id',
        'sktm_application_id',
        'recipient_name',
        'recipient_phone',
        'notification_type',
        'application_number',
        'message',
        'status',
        'api_response',
        'error_message',
        'retry_count',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'retry_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sktmApplication(): BelongsTo
    {
        return $this->belongsTo(SktmApplication::class);
    }

    /**
     * Label jenis notifikasi dalam Bahasa Indonesia.
     */
    public function typeLabel(): string
    {
        return match ($this->notification_type) {
            'submitted' => 'Pengajuan Baru',
            'verified_staff' => 'Verifikasi Staff',
            'need_revision' => 'Perbaikan Dokumen',
            'verified_supervisor' => 'Verifikasi Supervisor',
            'approved' => 'Persetujuan Kades',
            'rejected' => 'Penolakan Berkas',
            'document_ready' => 'Penerbitan Surat',
            'test_message' => 'Uji Coba Pengiriman',
            default => ucfirst(str_replace('_', ' ', $this->notification_type)),
        };
    }

    /**
     * Badge status HTML modern.
     */
    public function statusBadge(): string
    {
        return match ($this->status) {
            'sent' => '<span class="badge" style="background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;">✓ Terkirim</span>',
            'processing' => '<span class="badge" style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;">⏳ Proses</span>',
            'pending' => '<span class="badge" style="background:#fef3c7;color:#b45309;border:1px solid #fde68a;">⏱ Menunggu</span>',
            'failed' => '<span class="badge" style="background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;">✕ Gagal</span>',
            default => '<span class="badge">' . ucfirst($this->status) . '</span>',
        };
    }
}

