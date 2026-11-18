<?php

namespace App\Services;

use App\Models\SktmApplication;
use App\Models\User;
use App\Models\WhatsAppNotificationLog;
use App\Models\WhatsAppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonteWhatsAppService
{
    /**
     * Normalisasi nomor telepon menjadi format internasional Indonesia (62xxxxxxxxxxx).
     */
    public static function normalizePhoneNumber(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // Hapus karakter non-digit kecuali tanda plus di awal
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        if (empty($cleaned)) {
            return null;
        }

        // Jika diawali 08..., ganti 0 dengan 62
        if (str_starts_with($cleaned, '0')) {
            $cleaned = '62' . substr($cleaned, 1);
        } elseif (str_starts_with($cleaned, '8')) {
            // Jika diawali 8..., tambahkan 62
            $cleaned = '62' . $cleaned;
        }

        // Nomor ponsel Indonesia biasanya antara 10 hingga 15 digit
        if (strlen($cleaned) < 10 || strlen($cleaned) > 16) {
            return null;
        }

        return $cleaned;
    }

    /**
     * Ambil API Token yang tersimpan (dari Database Setting atau config/env).
     */
    public function getApiToken(): string
    {
        $token = WhatsAppSetting::get('api_token', config('whatsapp.api_token', ''));
        return trim((string) $token);
    }

    /**
     * Ambil API Send URL.
     */
    public function getApiUrl(): string
    {
        $url = WhatsAppSetting::get('api_url', config('whatsapp.api_url', 'https://api.fonnte.com/send'));
        return trim((string) $url);
    }

    /**
     * Cek apakah modul WhatsApp aktif.
     */
    public function isEnabled(): bool
    {
        return WhatsAppSetting::isEnabled();
    }

    /**
     * Cek apakah tipe notifikasi tertentu diaktifkan.
     */
    public function isTypeEnabled(string $type): bool
    {
        $settingKey = "notify_{$type}";
        $val = WhatsAppSetting::get($settingKey, config("whatsapp.notifications.{$type}", true));
        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Cek status koneksi device pada FONTE API.
     */
    public function checkDeviceStatus(): array
    {
        $token = $this->getApiToken();
        if (empty($token)) {
            return [
                'connected' => false,
                'status' => 'unconfigured',
                'message' => 'API Token FONTE belum dikonfigurasi.',
            ];
        }

        $deviceUrl = (string) WhatsAppSetting::get('device_check_url', config('whatsapp.device_check_url', 'https://api.fonnte.com/device'));

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => $token,
                ])
                ->post($deviceUrl);

            if ($response->successful()) {
                $body = $response->json();
                $deviceStatus = $body['device_status'] ?? ($body['status'] ?? 'unknown');
                $deviceStatusString = is_string($deviceStatus) ? strtolower($deviceStatus) : '';
                $isConnected = ($deviceStatus === 'connect' || $deviceStatus === 'connected' || ($body['status'] ?? false) === true);

                return [
                    'connected' => $isConnected,
                    'status' => $deviceStatus,
                    'device' => $body['device'] ?? ($body['name'] ?? 'Device Terhubung'),
                    'quota' => $body['quota'] ?? '-',
                    'expired' => $body['expired'] ?? '-',
                    'message' => $isConnected ? 'WhatsApp Gateway FONTE Aktif & Terhubung' : 'Device belum terhubung (Periksa scan QR di dashboard FONTE)',
                    'raw' => $body,
                ];
            }

            return [
                'connected' => false,
                'status' => 'error',
                'message' => 'Gagal menghubungi FONTE API: HTTP ' . $response->status(),
                'raw' => $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::warning('FONTE device status check error: ' . $e->getMessage());
            return [
                'connected' => false,
                'status' => 'timeout_or_error',
                'message' => 'Koneksi ke FONTE API mengalami timeout atau error jaringan: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Kirim notifikasi WhatsApp ke target dengan pencatatan log komprehensif.
     */
    public function send(
        string $recipientName,
        string $recipientPhone,
        string $notificationType,
        string $message,
        ?string $applicationNumber = null,
        ?User $user = null,
        ?SktmApplication $sktm = null
    ): WhatsAppNotificationLog {
        // 1. Normalisasi nomor telepon target
        $normalizedPhone = self::normalizePhoneNumber($recipientPhone);

        // 2. Catat Log Awal (Pending)
        $log = WhatsAppNotificationLog::create([
            'user_id' => $user?->id,
            'sktm_application_id' => $sktm?->id,
            'recipient_name' => $recipientName,
            'recipient_phone' => $normalizedPhone ?? $recipientPhone,
            'notification_type' => $notificationType,
            'application_number' => $applicationNumber,
            'message' => $message,
            'status' => 'pending',
            'retry_count' => 0,
        ]);

        // 2. Cek apakah gateway aktif
        if (! $this->isEnabled()) {
            $log->update([
                'status' => 'failed',
                'error_message' => 'Notifikasi WhatsApp dinonaktifkan pada pengaturan sistem.',
            ]);
            return $log;
        }

        // 3. Cek apakah jenis notifikasi ini diaktifkan
        if ($notificationType !== 'test_message' && ! $this->isTypeEnabled($notificationType)) {
            $log->update([
                'status' => 'failed',
                'error_message' => "Pengiriman untuk jenis notifikasi '{$notificationType}' dinonaktifkan oleh administrator.",
            ]);
            return $log;
        }

        // 4. Validasi nomor telepon
        $normalizedPhone = self::normalizePhoneNumber($recipientPhone);
        if (! $normalizedPhone) {
            $log->update([
                'status' => 'failed',
                'error_message' => "Nomor telepon '{$recipientPhone}' tidak valid atau tidak memenuhi format Indonesia.",
            ]);
            return $log;
        }

        // 5. Cek token
        $token = $this->getApiToken();
        if (empty($token)) {
            $log->update([
                'status' => 'failed',
                'error_message' => 'FONTE API Token belum diatur pada sistem.',
            ]);
            return $log;
        }

        // 6. Eksekusi pengiriman via HTTP Client FONTE
        $log->update(['status' => 'processing']);

        $apiUrl = $this->getApiUrl();
        $deviceId = WhatsAppSetting::get('device_id', config('whatsapp.device_id', ''));

        try {
            $payload = [
                'target' => $normalizedPhone,
                'message' => $message,
                'countryCode' => '62',
            ];

            if (!empty($deviceId)) {
                $payload['device'] = $deviceId;
            }

            $response = Http::timeout(config('whatsapp.timeout', 12))
                ->withHeaders([
                    'Authorization' => $token,
                ])
                ->post($apiUrl, $payload);

            $body = $response->json();
            $rawBody = $response->body();

            if ($response->successful() && isset($body['status']) && $body['status'] === true) {
                $log->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'api_response' => $rawBody,
                    'error_message' => null,
                ]);
            } else {
                $reason = $body['reason'] ?? ($body['message'] ?? 'Respon penolakan dari FONTE Gateway');
                $log->update([
                    'status' => 'failed',
                    'api_response' => $rawBody,
                    'error_message' => "Gagal kirim via FONTE: {$reason}",
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('FONTE WhatsApp Send Error: ' . $e->getMessage(), [
                'target' => $normalizedPhone,
                'log_id' => $log->id,
            ]);

            $log->update([
                'status' => 'failed',
                'error_message' => 'Koneksi Gateway Timeout / Gagal: ' . $e->getMessage(),
            ]);
        }

        return $log;
    }

    /**
     * Kirim ulang pesan yang gagal (Retry).
     */
    public function retryLog(WhatsAppNotificationLog $log): bool
    {
        $log->increment('retry_count');
        $log->update(['status' => 'processing', 'error_message' => null]);

        $normalizedPhone = self::normalizePhoneNumber($log->recipient_phone);
        if (! $normalizedPhone) {
            $log->update([
                'status' => 'failed',
                'error_message' => 'Nomor WhatsApp penerima tidak valid.',
            ]);
            return false;
        }

        $token = $this->getApiToken();
        if (empty($token)) {
            $log->update([
                'status' => 'failed',
                'error_message' => 'FONTE API Token belum diatur.',
            ]);
            return false;
        }

        try {
            $response = Http::timeout(12)
                ->withHeaders(['Authorization' => $token])
                ->post($this->getApiUrl(), [
                    'target' => $normalizedPhone,
                    'message' => $log->message,
                    'countryCode' => '62',
                ]);

            $body = $response->json();
            if ($response->successful() && isset($body['status']) && $body['status'] === true) {
                $log->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'api_response' => $response->body(),
                    'error_message' => null,
                ]);
                return true;
            }

            $reason = $body['reason'] ?? ($body['message'] ?? 'Respon penolakan API FONTE');
            $log->update([
                'status' => 'failed',
                'api_response' => $response->body(),
                'error_message' => "Retry gagal: {$reason}",
            ]);
            return false;
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => 'Retry timeout/error: ' . $e->getMessage(),
            ]);
            return false;
        }
    }

    // =========================================================================
    // TEMPLATE BUILDERS RESMI SIPEDES
    // =========================================================================

    /**
     * A. Masyarakat Mengajukan Surat
     */
    public static function buildSubmittedMessage(string $name, string $nomorPengajuan, string $jenisSurat, string $tanggal): string
    {
        return "Assalamu'alaikum Wr. Wb.\n\n"
            . "Halo {$name}.\n\n"
            . "Pengajuan surat Anda melalui SIPEDES berhasil diterima.\n\n"
            . "Nomor Pengajuan: {$nomorPengajuan}\n"
            . "Jenis Surat: {$jenisSurat}\n"
            . "Tanggal Pengajuan: {$tanggal}\n\n"
            . "Silakan pantau perkembangan pengajuan melalui website SIPEDES.\n\n"
            . "Terima kasih.";
    }

    /**
     * B.1 Staff Memeriksa: Berkas Lengkap
     */
    public static function buildVerifiedStaffMessage(string $name, string $nomorPengajuan, string $jenisSurat): string
    {
        return "Assalamu'alaikum Wr. Wb.\n\n"
            . "Halo {$name}.\n\n"
            . "Pengajuan surat Anda telah selesai diperiksa oleh Staff Pelayanan Desa dan akan dilanjutkan ke tahap verifikasi berikutnya.\n\n"
            . "Nomor Pengajuan: {$nomorPengajuan}\n"
            . "Jenis Surat: {$jenisSurat}\n\n"
            . "Silakan pantau perkembangan pengajuan melalui website SIPEDES.\n\n"
            . "Terima kasih.";
    }

    /**
     * B.2 Staff Memeriksa: Berkas Perlu Perbaikan
     */
    public static function buildNeedRevisionMessage(string $name, string $nomorPengajuan, string $jenisSurat, string $catatan): string
    {
        return "Assalamu'alaikum Wr. Wb.\n\n"
            . "Halo {$name}.\n\n"
            . "Pengajuan surat Anda memerlukan perbaikan dokumen.\n\n"
            . "Nomor Pengajuan: {$nomorPengajuan}\n"
            . "Jenis Surat: {$jenisSurat}\n"
            . "Catatan Petugas: {$catatan}\n\n"
            . "Silakan periksa kembali dokumen melalui website SIPEDES.\n\n"
            . "Terima kasih.";
    }

    /**
     * C.1 Supervisor Memeriksa: Lolos Verifikasi
     */
    public static function buildVerifiedSupervisorMessage(string $name, string $nomorPengajuan, string $jenisSurat): string
    {
        return "Assalamu'alaikum Wr. Wb.\n\n"
            . "Halo {$name}.\n\n"
            . "Pengajuan surat Anda telah selesai melalui tahap verifikasi Supervisor dan sedang menunggu proses selanjutnya.\n\n"
            . "Nomor Pengajuan: {$nomorPengajuan}\n"
            . "Jenis Surat: {$jenisSurat}\n\n"
            . "Silakan pantau perkembangan pengajuan melalui website SIPEDES.\n\n"
            . "Terima kasih.";
    }

    /**
     * C.2 Supervisor Memeriksa: Dikembalikan / Perlu Perbaikan
     */
    public static function buildSupervisorRevisionMessage(string $name, string $nomorPengajuan, string $jenisSurat, string $catatan): string
    {
        return "Assalamu'alaikum Wr. Wb.\n\n"
            . "Halo {$name}.\n\n"
            . "Pengajuan surat Anda memerlukan perbaikan.\n\n"
            . "Nomor Pengajuan: {$nomorPengajuan}\n"
            . "Jenis Surat: {$jenisSurat}\n"
            . "Catatan Supervisor: {$catatan}\n\n"
            . "Silakan lakukan perbaikan sesuai petunjuk pada sistem SIPEDES.\n\n"
            . "Terima kasih.";
    }

    /**
     * D.1 Kepala Desa Memberikan Persetujuan (Disetujui)
     */
    public static function buildApprovedMessage(string $name, string $nomorPengajuan, string $jenisSurat): string
    {
        return "Assalamu'alaikum Wr. Wb.\n\n"
            . "Selamat {$name}.\n\n"
            . "Pengajuan surat {$jenisSurat} Anda telah mendapatkan persetujuan Kepala Desa.\n\n"
            . "Nomor Pengajuan: {$nomorPengajuan}\n\n"
            . "Silakan periksa informasi penerbitan surat melalui sistem SIPEDES.\n\n"
            . "Terima kasih.";
    }

    /**
     * D.2 Kepala Desa Menolak Pengajuan
     */
    public static function buildRejectedMessage(string $name, string $nomorPengajuan, string $jenisSurat, string $alasan): string
    {
        return "Assalamu'alaikum Wr. Wb.\n\n"
            . "Halo {$name}.\n\n"
            . "Pengajuan surat Anda belum dapat disetujui.\n\n"
            . "Nomor Pengajuan: {$nomorPengajuan}\n"
            . "Jenis Surat: {$jenisSurat}\n"
            . "Alasan: {$alasan}\n\n"
            . "Silakan periksa informasi lebih lanjut melalui sistem SIPEDES atau hubungi petugas pelayanan desa.\n\n"
            . "Terima kasih.";
    }

    /**
     * E. Surat Selesai Diterbitkan
     */
    public static function buildDocumentReadyMessage(string $name, string $nomorPengajuan, string $jenisSurat, ?string $nomorSurat = null): string
    {
        $infoSurat = $nomorSurat ? "\nNomor Surat: {$nomorSurat}" : '';

        return "Assalamu'alaikum Wr. Wb.\n\n"
            . "Halo {$name}.\n\n"
            . "Surat {$jenisSurat} Anda telah selesai diproses dan diterbitkan melalui SIPEDES.\n\n"
            . "Nomor Pengajuan: {$nomorPengajuan}" . $infoSurat . "\n\n"
            . "Silakan mengikuti informasi pengambilan atau pengunduhan surat melalui sistem SIPEDES.\n\n"
            . "Terima kasih.";
    }
}

