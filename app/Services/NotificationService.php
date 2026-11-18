<?php

namespace App\Services;

use App\Jobs\SendWhatsAppNotificationJob;
use App\Models\AppNotification;
use App\Models\SktmApplication;
use App\Models\User;
use App\Services\FonteWhatsAppService;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Kirim notifikasi umum ke pengguna.
     */
    public function send(
        User $user,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
        ?SktmApplication $sktm = null
    ): AppNotification {
        return AppNotification::create([
            'user_id' => $user->id,
            'sktm_application_id' => $sktm?->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl ?? ($sktm ? route('warga.sktm.show', $sktm->id) : null),
            'is_read' => false,
        ]);
    }

    /**
     * 1. Notifikasi: Pengajuan berhasil dikirim.
     */
    public function notifySubmitted(SktmApplication $sktm): ?AppNotification
    {
        $user = $sktm->user;
        if (! $user) {
            return null;
        }

        $notification = $this->send(
            user: $user,
            type: 'application_submitted',
            title: 'Pengajuan Berhasil Dikirim',
            message: "Permohonan SKTM Anda dengan nomor tiket {$sktm->nomor_pengajuan} berhasil dikirimkan ke kantor desa dan sedang menunggu pemeriksaan petugas.",
            actionUrl: route('warga.sktm.show', $sktm->id),
            sktm: $sktm
        );

        if (!empty($user->phone_number)) {
            try {
                $msg = FonteWhatsAppService::buildSubmittedMessage(
                    name: $user->name,
                    nomorPengajuan: $sktm->nomor_pengajuan,
                    jenisSurat: $sktm->keperluan ?: 'SKTM',
                    tanggal: now()->translatedFormat('d F Y')
                );
                SendWhatsAppNotificationJob::dispatch(
                    recipientName: $user->name,
                    recipientPhone: $user->phone_number,
                    notificationType: 'submitted',
                    message: $msg,
                    applicationNumber: $sktm->nomor_pengajuan,
                    user: $user,
                    sktm: $sktm
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal dispatch WhatsApp submitted: ' . $e->getMessage());
            }
        }

        return $notification;
    }

    /**
     * 2. Notifikasi: Dokumen perlu diperbaiki.
     */
    public function notifyNeedRevision(SktmApplication $sktm, ?string $reason = null): ?AppNotification
    {
        $user = $sktm->user;
        if (! $user) {
            return null;
        }

        $detailReason = $reason ?: ($sktm->catatan ?: 'Periksa kelengkapan dan kejelasan dokumen persyaratan.');

        $notification = $this->send(
            user: $user,
            type: 'need_revision',
            title: 'Dokumen Perlu Diperbaiki',
            message: "Petugas meminta perbaikan pada permohonan SKTM {$sktm->nomor_pengajuan}. Catatan: \"{$detailReason}\"",
            actionUrl: route('warga.sktm.edit', $sktm->id),
            sktm: $sktm
        );

        if (!empty($user->phone_number)) {
            try {
                $msg = FonteWhatsAppService::buildNeedRevisionMessage(
                    name: $user->name,
                    nomorPengajuan: $sktm->nomor_pengajuan,
                    jenisSurat: $sktm->keperluan ?: 'SKTM',
                    catatan: $detailReason
                );
                SendWhatsAppNotificationJob::dispatch(
                    recipientName: $user->name,
                    recipientPhone: $user->phone_number,
                    notificationType: 'need_revision',
                    message: $msg,
                    applicationNumber: $sktm->nomor_pengajuan,
                    user: $user,
                    sktm: $sktm
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal dispatch WhatsApp need_revision: ' . $e->getMessage());
            }
        }

        return $notification;
    }

    /**
     * 3. Notifikasi: Pengajuan telah diverifikasi Staff.
     */
    public function notifyVerifiedByStaff(SktmApplication $sktm, ?string $notes = null): ?AppNotification
    {
        $user = $sktm->user;
        if (! $user) {
            return null;
        }

        $notification = $this->send(
            user: $user,
            type: 'verified_by_staff',
            title: 'Pengajuan Telah Diverifikasi Staff',
            message: "Kelengkapan berkas permohonan SKTM {$sktm->nomor_pengajuan} telah diverifikasi oleh Staff Desa dan diteruskan ke Supervisor untuk verifikasi faktual.",
            actionUrl: route('warga.sktm.show', $sktm->id),
            sktm: $sktm
        );

        if (!empty($user->phone_number)) {
            try {
                $msg = FonteWhatsAppService::buildVerifiedStaffMessage(
                    name: $user->name,
                    nomorPengajuan: $sktm->nomor_pengajuan,
                    jenisSurat: $sktm->keperluan ?: 'SKTM'
                );
                SendWhatsAppNotificationJob::dispatch(
                    recipientName: $user->name,
                    recipientPhone: $user->phone_number,
                    notificationType: 'verified_staff',
                    message: $msg,
                    applicationNumber: $sktm->nomor_pengajuan,
                    user: $user,
                    sktm: $sktm
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal dispatch WhatsApp verified_staff: ' . $e->getMessage());
            }
        }

        return $notification;
    }

    /**
     * 4. Notifikasi: Pengajuan telah diverifikasi Supervisor.
     */
    public function notifyVerifiedBySupervisor(SktmApplication $sktm, ?string $notes = null): ?AppNotification
    {
        $user = $sktm->user;
        if (! $user) {
            return null;
        }

        $notification = $this->send(
            user: $user,
            type: 'verified_by_supervisor',
            title: 'Pengajuan Telah Diverifikasi Supervisor',
            message: "Verifikasi kelayakan faktual permohonan SKTM {$sktm->nomor_pengajuan} telah disetujui Supervisor dan diteruskan ke Kepala Desa untuk persetujuan akhir.",
            actionUrl: route('warga.sktm.show', $sktm->id),
            sktm: $sktm
        );

        if (!empty($user->phone_number)) {
            try {
                $msg = FonteWhatsAppService::buildVerifiedSupervisorMessage(
                    name: $user->name,
                    nomorPengajuan: $sktm->nomor_pengajuan,
                    jenisSurat: $sktm->keperluan ?: 'SKTM'
                );
                SendWhatsAppNotificationJob::dispatch(
                    recipientName: $user->name,
                    recipientPhone: $user->phone_number,
                    notificationType: 'verified_supervisor',
                    message: $msg,
                    applicationNumber: $sktm->nomor_pengajuan,
                    user: $user,
                    sktm: $sktm
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal dispatch WhatsApp verified_supervisor: ' . $e->getMessage());
            }
        }

        return $notification;
    }

    /**
     * 5. Notifikasi: Pengajuan disetujui Kepala Desa.
     */
    public function notifyApproved(SktmApplication $sktm): ?AppNotification
    {
        $user = $sktm->user;
        if (! $user) {
            return null;
        }

        $nomorInfo = $sktm->nomor_surat ? " dengan Nomor Surat: {$sktm->nomor_surat}" : '';

        $notification = $this->send(
            user: $user,
            type: 'approved',
            title: 'Pengajuan Disetujui Kepala Desa',
            message: "Permohonan SKTM {$sktm->nomor_pengajuan} telah disetujui secara resmi oleh Kepala Desa{$nomorInfo}.",
            actionUrl: route('warga.sktm.show', $sktm->id),
            sktm: $sktm
        );

        if (!empty($user->phone_number)) {
            try {
                $msg = FonteWhatsAppService::buildApprovedMessage(
                    name: $user->name,
                    nomorPengajuan: $sktm->nomor_pengajuan,
                    jenisSurat: $sktm->keperluan ?: 'SKTM'
                );
                SendWhatsAppNotificationJob::dispatch(
                    recipientName: $user->name,
                    recipientPhone: $user->phone_number,
                    notificationType: 'approved',
                    message: $msg,
                    applicationNumber: $sktm->nomor_pengajuan,
                    user: $user,
                    sktm: $sktm
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal dispatch WhatsApp approved: ' . $e->getMessage());
            }
        }

        return $notification;
    }

    /**
     * 6. Notifikasi: SKTM sudah dapat di-download.
     */
    public function notifyDocumentReady(SktmApplication $sktm): ?AppNotification
    {
        $user = $sktm->user;
        if (! $user) {
            return null;
        }

        $notification = $this->send(
            user: $user,
            type: 'document_ready',
            title: 'SKTM Sudah Dapat Di-download',
            message: "Surat Keterangan Tidak Mampu resmi ({$sktm->nomor_surat}) telah diterbitkan dan siap diunduh dalam format PDF.",
            actionUrl: route('warga.sktm.show', $sktm->id),
            sktm: $sktm
        );

        if (!empty($user->phone_number)) {
            try {
                $msg = FonteWhatsAppService::buildDocumentReadyMessage(
                    name: $user->name,
                    nomorPengajuan: $sktm->nomor_pengajuan,
                    jenisSurat: $sktm->keperluan ?: 'SKTM',
                    nomorSurat: $sktm->nomor_surat
                );
                SendWhatsAppNotificationJob::dispatch(
                    recipientName: $user->name,
                    recipientPhone: $user->phone_number,
                    notificationType: 'document_ready',
                    message: $msg,
                    applicationNumber: $sktm->nomor_pengajuan,
                    user: $user,
                    sktm: $sktm
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal dispatch WhatsApp document_ready: ' . $e->getMessage());
            }
        }

        return $notification;
    }

    /**
     * 7. Notifikasi: Pengajuan ditolak.
     */
    public function notifyRejected(SktmApplication $sktm, ?string $reason = null): ?AppNotification
    {
        $user = $sktm->user;
        if (! $user) {
            return null;
        }

        $detailReason = $reason ?: ($sktm->catatan ?: 'Tidak memenuhi persyaratan kriteria pelayanan.');

        $notification = $this->send(
            user: $user,
            type: 'rejected',
            title: 'Pengajuan Ditolak',
            message: "Mohon maaf, permohonan SKTM {$sktm->nomor_pengajuan} ditolak. Alasan: \"{$detailReason}\"",
            actionUrl: route('warga.sktm.show', $sktm->id),
            sktm: $sktm
        );

        if (!empty($user->phone_number)) {
            try {
                $msg = FonteWhatsAppService::buildRejectedMessage(
                    name: $user->name,
                    nomorPengajuan: $sktm->nomor_pengajuan,
                    jenisSurat: $sktm->keperluan ?: 'SKTM',
                    alasan: $detailReason
                );
                SendWhatsAppNotificationJob::dispatch(
                    recipientName: $user->name,
                    recipientPhone: $user->phone_number,
                    notificationType: 'rejected',
                    message: $msg,
                    applicationNumber: $sktm->nomor_pengajuan,
                    user: $user,
                    sktm: $sktm
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal dispatch WhatsApp rejected: ' . $e->getMessage());
            }
        }

        return $notification;
    }
}
