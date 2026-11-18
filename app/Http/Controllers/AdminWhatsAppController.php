<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppNotificationLog;
use App\Models\WhatsAppSetting;
use App\Services\FonteWhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminWhatsAppController extends Controller
{
    /**
     * Tampilkan halaman pengaturan WhatsApp FONTE Gateway.
     */
    public function settings(FonteWhatsAppService $service): View
    {
        $settings = [
            'enabled' => WhatsAppSetting::isEnabled(),
            'api_token_set' => !empty($service->getApiToken()),
            'device_id' => WhatsAppSetting::get('device_id', config('whatsapp.device_id', '')),
            'api_url' => WhatsAppSetting::get('api_url', config('whatsapp.api_url', 'https://api.fonnte.com/send')),
            'device_check_url' => WhatsAppSetting::get('device_check_url', config('whatsapp.device_check_url', 'https://api.fonnte.com/device')),
            'notify_submitted' => WhatsAppSetting::get('notify_submitted', config('whatsapp.notifications.submitted', true)),
            'notify_verified_staff' => WhatsAppSetting::get('notify_verified_staff', config('whatsapp.notifications.verified_staff', true)),
            'notify_need_revision' => WhatsAppSetting::get('notify_need_revision', config('whatsapp.notifications.need_revision', true)),
            'notify_verified_supervisor' => WhatsAppSetting::get('notify_verified_supervisor', config('whatsapp.notifications.verified_supervisor', true)),
            'notify_approved' => WhatsAppSetting::get('notify_approved', config('whatsapp.notifications.approved', true)),
            'notify_rejected' => WhatsAppSetting::get('notify_rejected', config('whatsapp.notifications.rejected', true)),
            'notify_document_ready' => WhatsAppSetting::get('notify_document_ready', config('whatsapp.notifications.document_ready', true)),
        ];

        // Hitung statistik log
        $stats = [
            'total' => WhatsAppNotificationLog::count(),
            'sent' => WhatsAppNotificationLog::where('status', 'sent')->count(),
            'failed' => WhatsAppNotificationLog::where('status', 'failed')->count(),
            'pending' => WhatsAppNotificationLog::whereIn('status', ['pending', 'processing'])->count(),
        ];

        $recentLogs = WhatsAppNotificationLog::latest()->take(6)->get();

        return view('admin.whatsapp.settings', compact('settings', 'stats', 'recentLogs'));
    }

    /**
     * Simpan konfigurasi WhatsApp FONTE Gateway.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => 'nullable|boolean',
            'api_token' => 'nullable|string|max:255',
            'device_id' => 'nullable|string|max:100',
            'api_url' => 'nullable|url|max:255',
            'device_check_url' => 'nullable|url|max:255',
            'notify_submitted' => 'nullable|boolean',
            'notify_verified_staff' => 'nullable|boolean',
            'notify_need_revision' => 'nullable|boolean',
            'notify_verified_supervisor' => 'nullable|boolean',
            'notify_approved' => 'nullable|boolean',
            'notify_rejected' => 'nullable|boolean',
            'notify_document_ready' => 'nullable|boolean',
        ]);

        WhatsAppSetting::set('enabled', $request->has('enabled') ? 'true' : 'false');
        
        // Hanya update API token jika diisi dan bukan placeholder bintang
        if (!empty($validated['api_token']) && !str_contains($validated['api_token'], '••••')) {
            WhatsAppSetting::set('api_token', trim($validated['api_token']));
        }

        if (isset($validated['device_id'])) {
            WhatsAppSetting::set('device_id', trim($validated['device_id']));
        }

        if (!empty($validated['api_url'])) {
            WhatsAppSetting::set('api_url', trim($validated['api_url']));
        }

        if (!empty($validated['device_check_url'])) {
            WhatsAppSetting::set('device_check_url', trim($validated['device_check_url']));
        }

        // Tipe notifikasi
        $types = ['submitted', 'verified_staff', 'need_revision', 'verified_supervisor', 'approved', 'rejected', 'document_ready'];
        foreach ($types as $type) {
            WhatsAppSetting::set("notify_{$type}", $request->has("notify_{$type}") ? 'true' : 'false');
        }

        return redirect()->route('admin.whatsapp.settings')
            ->with('success', 'Pengaturan WhatsApp FONTE Gateway berhasil diperbarui.');
    }

    /**
     * Uji coba pengiriman pesan langsung (Test Send).
     */
    public function testSend(Request $request, FonteWhatsAppService $service): RedirectResponse
    {
        $validated = $request->validate([
            'recipient_phone' => 'required|string|min:9|max:20',
            'test_message' => 'nullable|string|max:500',
        ]);

        $defaultMessage = "Assalamu'alaikum Wr. Wb.\n\n"
            . "Ini adalah pesan uji coba (Test Notification) dari Sistem Pelayanan Desa (SIPEDES) melalui WhatsApp Gateway FONTE.\n\n"
            . "Jika Anda menerima pesan ini, integrasi WhatsApp Gateway telah berfungsi dengan baik.\n\n"
            . "Waktu Uji Coba: " . now()->translatedFormat('d F Y H:i:s') . " WIB\n\n"
            . "Terima kasih.";

        $message = !empty($validated['test_message']) ? trim($validated['test_message']) : $defaultMessage;

        $log = $service->send(
            recipientName: 'Uji Coba Admin',
            recipientPhone: $validated['recipient_phone'],
            notificationType: 'test',
            message: $message,
            applicationNumber: 'TEST-' . strtoupper(bin2hex(random_bytes(3)))
        );

        if ($log->status === 'sent') {
            return redirect()->route('admin.whatsapp.settings')
                ->with('success', "Pesan uji coba berhasil terkirim ke {$validated['recipient_phone']}!");
        }

        return redirect()->route('admin.whatsapp.settings')
            ->with('error', "Gagal mengirim pesan uji coba: {$log->error_message}");
    }

    /**
     * Cek status koneksi perangkat ke FONTE Gateway.
     */
    public function checkDevice(FonteWhatsAppService $service): RedirectResponse
    {
        $status = $service->checkDeviceStatus();

        if ($status['connected']) {
            $quotaInfo = isset($status['quota']) ? " (Sisa Kuota: {$status['quota']})" : '';
            $deviceInfo = !empty($status['device']) ? " - Perangkat: {$status['device']}" : '';
            return redirect()->route('admin.whatsapp.settings')
                ->with('success', "Perangkat FONTE terhubung aktif!{$deviceInfo}{$quotaInfo}");
        }

        return redirect()->route('admin.whatsapp.settings')
            ->with('error', "Perangkat FONTE tidak terhubung atau error: " . ($status['message'] ?? 'Tidak ada respon.'));
    }

    /**
     * Tampilkan riwayat log pengiriman notifikasi WhatsApp.
     */
    public function logs(Request $request): View
    {
        $query = WhatsAppNotificationLog::with(['user', 'sktmApplication'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('notification_type', $request->type);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('recipient_name', 'like', "%{$search}%")
                  ->orWhere('recipient_phone', 'like', "%{$search}%")
                  ->orWhere('application_number', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $logs = $query->paginate(15)->withQueryString();

        $stats = [
            'total' => WhatsAppNotificationLog::count(),
            'sent' => WhatsAppNotificationLog::where('status', 'sent')->count(),
            'failed' => WhatsAppNotificationLog::where('status', 'failed')->count(),
            'pending' => WhatsAppNotificationLog::whereIn('status', ['pending', 'processing'])->count(),
        ];

        return view('admin.whatsapp.logs', compact('logs', 'stats'));
    }

    /**
     * Kirim ulang pesan yang gagal (Retry).
     */
    public function retry(WhatsAppNotificationLog $log, FonteWhatsAppService $service): RedirectResponse
    {
        $success = $service->retryLog($log);

        if ($success) {
            return back()->with('success', "Pesan notifikasi kepada {$log->recipient_name} ({$log->recipient_phone}) berhasil dikirim ulang.");
        }

        return back()->with('error', "Gagal mengirim ulang pesan: {$log->fresh()->error_message}");
    }
}

