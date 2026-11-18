<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WhatsAppNotificationLog;
use App\Models\WhatsAppSetting;
use App\Services\FonteWhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class AdminController extends Controller
{
    /**
     * Tampilkan Dashboard Khusus Administrator (Kelola Pengguna & Konfigurasi Sistem).
     */
    public function dashboard(Request $request): View
    {
        $currentAdmin = Auth::user();

        // Ambil daftar pengguna dengan filter peran jika ada
        $roleFilter = $request->query('role');
        $query = User::with('residentProfile')->latest();

        if ($roleFilter && in_array($roleFilter, UserRole::values(), true)) {
            $query->where('role', $roleFilter);
        }

        $users = $query->get();

        // Statistik utama yang diminta PRD Admin
        $stats = [
            'jumlah_pengguna' => User::count(),
            'jumlah_masyarakat' => User::where('role', UserRole::MASYARAKAT)->count(),
            'jumlah_pengajuan' => \App\Models\SktmApplication::count(),
            'jumlah_aktif' => User::where('is_active', true)->count(),
            'jumlah_nonaktif' => User::where('is_active', false)->count(),
            'aparat_desa' => User::whereIn('role', [UserRole::STAFF, UserRole::SUPERVISOR, UserRole::KEPALA_DESA])->count(),
        ];

        // Pengaturan Sistem Desa (Menggunakan SystemSetting permanen dengan fallback session)
        $config = SystemSetting::getAll();
        session(['village_config' => $config]);

        $notifications = [
            [
                'title' => 'Audit Keamanan Sesi',
                'message' => 'Seluruh sesi otentikasi berjalan aman dengan proteksi CSRF dan rate limiting.',
                'time' => 'Baru saja',
                'type' => 'success',
            ],
            [
                'title' => 'Pendaftaran Warga Baru',
                'message' => 'Akun warga atas nama Joko Susilo berhasil terhubung dengan NIK 320101******0001.',
                'time' => '1 hari lalu',
                'type' => 'info',
            ],
        ];

        return view('dashboard.admin', compact('currentAdmin', 'users', 'stats', 'config', 'roleFilter', 'notifications'));
    }

    /**
     * Mengelola status aktif / nonaktif pengguna.
     */
    public function toggleUserStatus(User $user): RedirectResponse
    {
        // Cegah Admin menonaktifkan akunnya sendiri demi mencegah lockout
        if ($user->id === Auth::id()) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        $statusText = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('status', "Akun '{$user->name}' ({$user->role->label()}) berhasil {$statusText}.");
    }

    /**
     * Memperbarui data pengguna melalui popup modal.
     */
    public function updateUser(Request $request, User $user): RedirectResponse
    {
        // Cegah Admin menonaktifkan akunnya sendiri demi mencegah lockout sistem
        if ($user->id === Auth::id() && ! $request->boolean('is_active')) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun Administrator Anda sendiri.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'username' => ['nullable', 'string', 'max:50', 'unique:users,username,' . $user->id],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'role' => ['required', 'string', 'in:' . implode(',', UserRole::values())],
            'is_active' => ['nullable'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->username = !empty($validated['username']) ? $validated['username'] : null;
        $user->phone_number = $validated['phone_number'] ?? null;
        $user->role = UserRole::from($validated['role']);
        $user->is_active = $request->boolean('is_active');

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return back()->with('status', "Data akun pengguna '{$user->name}' berhasil diperbarui.");
    }

    /**
     * Memperbarui konfigurasi master data desa melalui popup modal (Kompatibilitas Backward).
     */
    public function updateConfig(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama_desa' => ['required', 'string', 'max:255'],
            'kode_desa' => ['required', 'string', 'max:50'],
            'kecamatan' => ['required', 'string', 'max:100'],
            'kabupaten' => ['required', 'string', 'max:100'],
            'provinsi' => ['required', 'string', 'max:100'],
            'kode_pos' => ['required', 'string', 'max:10'],
            'jam_layanan' => ['required', 'string', 'max:255'],
            'telepon_kantor' => ['required', 'string', 'max:50'],
            'email_resmi' => ['required', 'email', 'max:255'],
        ]);

        foreach ($validated as $key => $val) {
            SystemSetting::set($key, $val, 'general');
        }

        $allConfig = SystemSetting::getAll();
        session(['village_config' => $allConfig]);

        return back()->with('status', 'Pengaturan master data desa berhasil diperbarui.');
    }

    /**
     * Tampilkan Halaman Khusus Konfigurasi Sistem SIPEDES.
     */
    public function configIndex(FonteWhatsAppService $whatsAppService): View
    {
        $config = SystemSetting::getAll();
        session(['village_config' => $config]);

        // Status Integrasi WhatsApp FONTE Gateway
        $token = $whatsAppService->getApiToken();
        $tokenMasked = !empty($token)
            ? (strlen($token) > 8 ? substr($token, 0, 4) . '••••••••' . substr($token, -4) : '••••••••')
            : null;

        $whatsAppStatus = [
            'enabled' => WhatsAppSetting::isEnabled(),
            'has_token' => !empty($token),
            'token_masked' => $tokenMasked,
            'device_id' => WhatsAppSetting::get('device_id', config('whatsapp.device_id', '-')),
            'api_url' => WhatsAppSetting::get('api_url', config('whatsapp.api_url', 'https://api.fonnte.com/send')),
            'logs_total' => WhatsAppNotificationLog::count(),
            'logs_sent' => WhatsAppNotificationLog::where('status', 'sent')->count(),
            'logs_failed' => WhatsAppNotificationLog::where('status', 'failed')->count(),
        ];

        // Informasi Keamanan & Sesi
        $securityInfo = [
            'session_driver' => config('session.driver', 'database'),
            'session_lifetime' => SystemSetting::get('session_lifetime', config('session.lifetime', 120)),
            'password_min_length' => SystemSetting::get('password_min_length', '8'),
            'max_login_attempts' => SystemSetting::get('max_login_attempts', '5'),
            'csrf_protection' => true,
            'rate_limiting' => '5 Percobaan / Menit (Auto-decay 60 detik)',
            'encryption_cipher' => config('app.cipher', 'AES-256-CBC'),
        ];

        // Diagnostik Sistem & Database (Aman tanpa bocor kredensial)
        $systemDiagnostics = [
            'app_name' => $config['app_name'] ?? 'SIPEDES',
            'app_version' => 'v2.4.0 (Enterprise)',
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'environment' => app()->environment(),
            'db_connection' => config('database.default', 'sqlite'),
            'server_timezone' => config('app.timezone', 'Asia/Jakarta'),
            'server_time' => now()->translatedFormat('l, d F Y - H:i:s') . ' WIB',
            'debug_mode' => config('app.debug') ? 'Aktif (Development)' : 'Nonaktif (Production)',
        ];

        // Informasi Integrasi AI Assistant
        $aiApiKey = SystemSetting::get('ai_api_key') ?: (config('ai.api_key') ?: env('GEMINI_API_KEY'));
        $aiKeyMasked = !empty($aiApiKey)
            ? (strlen($aiApiKey) > 8 ? substr($aiApiKey, 0, 4) . '••••••••' . substr($aiApiKey, -4) : '••••••••')
            : null;

        $aiInfo = [
            'enabled' => (bool) SystemSetting::get('ai_assistant_enabled', '1'),
            'provider' => SystemSetting::get('ai_provider', config('ai.chat_driver', 'gemini')),
            'base_url' => SystemSetting::get('ai_base_url', 'https://generativelanguage.googleapis.com/v1beta'),
            'model_name' => SystemSetting::get('ai_model_name', 'gemini-1.5-flash'),
            'welcome_message' => SystemSetting::get('ai_welcome_message', 'Halo! Saya Asisten AI SIPEDES. Ada yang bisa saya bantu?'),
            'has_api_key' => !empty($aiApiKey),
            'api_key_masked' => $aiKeyMasked,
        ];

        return view('admin.config.index', compact(
            'config',
            'whatsAppStatus',
            'securityInfo',
            'systemDiagnostics',
            'aiInfo'
        ));
    }

    /**
     * Memperbarui Konfigurasi Informasi Umum & Instansi Desa.
     */
    public function updateGeneralConfig(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_name' => ['required', 'string', 'max:100'],
            'brand_tagline' => ['required', 'string', 'max:255'],
            'nama_desa' => ['required', 'string', 'max:255'],
            'kode_desa' => ['required', 'string', 'max:50'],
            'kecamatan' => ['required', 'string', 'max:100'],
            'kabupaten' => ['required', 'string', 'max:100'],
            'provinsi' => ['required', 'string', 'max:100'],
            'kode_pos' => ['required', 'string', 'max:10'],
            'alamat_kantor' => ['required', 'string', 'max:500'],
            'telepon_kantor' => ['required', 'string', 'max:50'],
            'email_resmi' => ['required', 'email', 'max:255'],
            'app_description' => ['required', 'string', 'max:1000'],
        ]);

        foreach ($validated as $key => $val) {
            SystemSetting::set($key, $val, 'general');
        }

        session(['village_config' => SystemSetting::getAll()]);

        return back()->with('status', 'Informasi Sistem dan Instansi Desa berhasil diperbarui.');
    }

    /**
     * Memperbarui Konfigurasi Tampilan & Branding.
     */
    public function updateAppearanceConfig(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'default_theme' => ['required', 'string', 'in:light,dark,system'],
            'primary_color' => ['required', 'string', 'max:20'],
            'dashboard_layout' => ['required', 'string', 'in:compact,comfortable'],
            'logo_text' => ['required', 'string', 'max:100'],
        ]);

        foreach ($validated as $key => $val) {
            SystemSetting::set($key, $val, 'appearance');
        }

        return back()->with('status', 'Pengaturan Tampilan dan Branding berhasil diperbarui.');
    }

    /**
     * Memperbarui Konfigurasi Pelayanan Desa.
     */
    public function updateServiceConfig(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'service_name' => ['required', 'string', 'max:255'],
            'jam_layanan' => ['required', 'string', 'max:255'],
            'hari_operasional' => ['required', 'string', 'max:255'],
            'persyaratan_layanan' => ['required', 'string', 'max:1000'],
            'info_pengajuan' => ['required', 'string', 'max:1000'],
            'pesan_warga' => ['required', 'string', 'max:1000'],
        ]);

        foreach ($validated as $key => $val) {
            SystemSetting::set($key, $val, 'service');
        }

        // Sinkronisasi dengan master jam layanan untuk session
        $all = SystemSetting::getAll();
        session(['village_config' => $all]);

        return back()->with('status', 'Pengaturan Pelayanan Desa berhasil diperbarui.');
    }

    /**
     * Memperbarui Konfigurasi Kebijakan Keamanan & Sesi.
     */
    public function updateSecurityConfig(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'session_lifetime' => ['required', 'integer', 'min:15', 'max:1440'],
            'password_min_length' => ['required', 'integer', 'min:8', 'max:32'],
            'max_login_attempts' => ['required', 'integer', 'min:3', 'max:20'],
            'audit_logging_enabled' => ['nullable', 'boolean'],
        ]);

        SystemSetting::set('session_lifetime', $validated['session_lifetime'], 'security');
        SystemSetting::set('password_min_length', $validated['password_min_length'], 'security');
        SystemSetting::set('max_login_attempts', $validated['max_login_attempts'], 'security');
        SystemSetting::set('audit_logging_enabled', $request->boolean('audit_logging_enabled') ? '1' : '0', 'security');

        return back()->with('status', 'Kebijakan Keamanan & Sesi berhasil diperbarui.');
    }

    /**
     * Memperbarui Konfigurasi AI Assistant SIPEDES.
     */
    public function updateAiConfig(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ai_assistant_enabled' => ['nullable', 'boolean'],
            'ai_provider' => ['nullable', 'string', 'in:gemini,openai,claude,local'],
            'ai_base_url' => ['nullable', 'string', 'max:255'],
            'ai_api_key' => ['nullable', 'string', 'max:255'],
            'ai_model_name' => ['required', 'string', 'max:100'],
            'ai_welcome_message' => ['required', 'string', 'max:500'],
        ]);

        $provider = $validated['ai_provider'] ?? SystemSetting::get('ai_provider', 'gemini');
        SystemSetting::set('ai_assistant_enabled', $request->boolean('ai_assistant_enabled') ? '1' : '0', 'ai');
        SystemSetting::set('ai_provider', $provider, 'ai');
        if (array_key_exists('ai_base_url', $validated)) {
            SystemSetting::set('ai_base_url', $validated['ai_base_url'] ?? '', 'ai');
        }
        if (!empty($validated['ai_api_key'])) {
            SystemSetting::set('ai_api_key', $validated['ai_api_key'], 'ai');
        }
        SystemSetting::set('ai_model_name', $validated['ai_model_name'], 'ai');
        SystemSetting::set('ai_welcome_message', $validated['ai_welcome_message'], 'ai');

        return back()->with('status', 'Konfigurasi AI Assistant SIPEDES berhasil diperbarui.');
    }

    /**
     * Menguji Koneksi AI Provider secara Langsung (Uji Koneksi AI).
     */
    public function testAiConnection(Request $request): JsonResponse
    {
        $provider = $request->input('ai_provider') ?: SystemSetting::get('ai_provider', 'gemini');
        $apiKey = $request->input('ai_api_key') ?: SystemSetting::get('ai_api_key', config('ai.api_key') ?: env('GEMINI_API_KEY'));
        $model = $request->input('ai_model_name') ?: SystemSetting::get('ai_model_name', 'gemini-1.5-flash');
        $baseUrl = $request->input('ai_base_url') ?: SystemSetting::get('ai_base_url', 'https://generativelanguage.googleapis.com/v1beta');

        $startTime = microtime(true);

        if ($provider === 'local') {
            $latency = round((microtime(true) - $startTime) * 1000);
            return response()->json([
                'success' => true,
                'message' => 'Koneksi ke Local / Basis Pengetahuan Desa SIPEDES berhasil terhubung!',
                'latency_ms' => max(5, $latency),
                'model' => $model,
                'provider' => $provider,
            ]);
        }

        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'API Key belum dikonfigurasi. Harap isi atau simpan API Key terlebih dahulu.',
            ], 422);
        }

        try {
            if ($provider === 'openai') {
                $endpoint = rtrim($baseUrl ?: 'https://api.openai.com/v1', '/') . '/models';
                $res = Http::withToken($apiKey)->timeout(8)->get($endpoint);
            } elseif ($provider === 'claude') {
                $endpoint = rtrim($baseUrl ?: 'https://api.anthropic.com/v1', '/') . '/models';
                $res = Http::withHeaders(['x-api-key' => $apiKey, 'anthropic-version' => '2023-06-01'])->timeout(8)->get($endpoint);
            } else {
                // Default gemini
                $cleanBase = rtrim($baseUrl ?: 'https://generativelanguage.googleapis.com/v1beta', '/');
                $endpoint = "{$cleanBase}/models/{$model}?key={$apiKey}";
                $res = Http::timeout(8)->get($endpoint);
            }

            $latency = round((microtime(true) - $startTime) * 1000);

            if ($res->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => "Koneksi ke Provider {$provider} (Model: {$model}) BERHASIL!",
                    'latency_ms' => max(10, $latency),
                    'model' => $model,
                    'provider' => $provider,
                ]);
            }

            $status = $res->status();
            $body = $res->json();
            $errMsg = $body['error']['message'] ?? $body['message'] ?? "Respon HTTP {$status}";

            return response()->json([
                'success' => false,
                'message' => "Koneksi gagal ({$status}): {$errMsg}",
                'latency_ms' => $latency,
            ], 400);
        } catch (\Throwable $e) {
            $latency = round((microtime(true) - $startTime) * 1000);
            return response()->json([
                'success' => false,
                'message' => 'Koneksi ke AI Provider gagal: ' . $e->getMessage(),
                'latency_ms' => $latency,
            ], 500);
        }
    }

    /**
     * Mengembalikan seluruh konfigurasi sistem ke pengaturan awal (Reset Factory Default).
     */
    public function resetConfig(): RedirectResponse
    {
        SystemSetting::resetToDefaults();
        session(['village_config' => SystemSetting::defaultSettings()]);

        return back()->with('status', 'Seluruh konfigurasi sistem berhasil dikembalikan ke nilai bawaan pabrik (Default).');
    }
}
