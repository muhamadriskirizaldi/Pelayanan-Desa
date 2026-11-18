<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $table = 'system_settings';

    protected $fillable = [
        'group',
        'key',
        'value',
    ];

    /**
     * Nilai default konfigurasi sistem SIPEDES.
     */
    public static function defaultSettings(): array
    {
        return [
            // 1. Informasi Sistem & Desa
            'app_name' => 'SIPEDES',
            'brand_tagline' => 'Sistem Pelayanan Desa Digital',
            'nama_desa' => 'Desa Sukamaju',
            'kode_desa' => '32.01.01.2001',
            'kecamatan' => 'Cibinong',
            'kabupaten' => 'Kabupaten Bogor',
            'provinsi' => 'Jawa Barat',
            'kode_pos' => '16913',
            'alamat_kantor' => 'Jl. Raya Cibinong - Sukamaju No. 42, RT 003 / RW 002',
            'telepon_kantor' => '(021) 876-5432',
            'email_resmi' => 'pelayanan@sukamaju.desa.id',
            'status_database' => 'SQLite (Active & Encrypted)',
            'status_ai' => 'Ready for Integration (Tahap Berikutnya)',
            'app_description' => 'Sistem Informasi Pelayanan Desa Terpadu Digital untuk mempermudah permohonan, verifikasi, penerbitan surat kependudukan secara cepat, transparan, dan akuntabel.',

            // 2. Pengaturan Tampilan
            'default_theme' => 'system', // light, dark, system
            'primary_color' => '#059669', // Emerald Green
            'dashboard_layout' => 'comfortable', // compact, comfortable
            'logo_text' => 'SIPEDES Digital',

            // 3. Pelayanan Desa
            'service_name' => 'Surat Keterangan Tidak Mampu (SKTM) & Administrasi Kependudukan',
            'jam_layanan' => 'Senin - Jumat, 08.00 - 15.00 WIB',
            'hari_operasional' => 'Senin s.d. Jumat (Sabtu, Minggu & Hari Libur Nasional Tutup)',
            'persyaratan_layanan' => 'Kartu Tanda Penduduk (KTP), Kartu Keluarga (KK), Surat Pengantar RT/RW, dan Berkas Pendukung yang Sah.',
            'info_pengajuan' => 'Pengajuan surat diproses berjenjang: 1) Pemeriksaan berkas Staff, 2) Verifikasi kelayakan Supervisor, dan 3) Persetujuan & Penandatanganan Kepala Desa.',
            'pesan_warga' => 'Pastikan berkas foto/scan dokumen KTP dan KK Anda jelas dan tidak buram untuk mempercepat persetujuan.',

            // 4. Keamanan & Sesi
            'session_lifetime' => '120', // menit
            'password_min_length' => '8',
            'max_login_attempts' => '5',
            'audit_logging_enabled' => '1',

            // 5. AI Assistant
            'ai_assistant_enabled' => '1',
            'ai_provider' => 'gemini',
            'ai_base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'ai_api_key' => '',
            'ai_model_name' => 'gemini-1.5-flash',
            'ai_welcome_message' => 'Halo! Saya Asisten AI SIPEDES. Ada yang bisa saya bantu mengenai pengajuan surat atau administrasi desa hari ini?',
        ];
    }

    /**
     * Ambil nilai pengaturan berdasarkan key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $setting = static::where('key', $key)->first();
            if ($setting !== null && $setting->value !== null) {
                return $setting->value;
            }
        } catch (\Throwable $e) {
            // Abaikan jika tabel belum ada atau gagal koneksi
        }

        $defaults = static::defaultSettings();
        return $defaults[$key] ?? $default;
    }

    /**
     * Simpan atau update nilai pengaturan.
     */
    public static function set(string $key, mixed $value, string $group = 'general'): void
    {
        static::updateOrCreate(
            ['key' => $key],
            [
                'group' => $group,
                'value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
            ]
        );
    }

    /**
     * Ambil seluruh konfigurasi gabungan (default + database).
     */
    public static function getAll(): array
    {
        $settings = static::defaultSettings();

        try {
            $records = static::all();
            foreach ($records as $record) {
                if ($record->value !== null) {
                    $settings[$record->key] = $record->value;
                }
            }
        } catch (\Throwable $e) {
            // Abaikan error DB fallback ke defaults
        }

        return $settings;
    }

    /**
     * Kembalikan semua pengaturan ke nilai bawaan pabrik (default).
     */
    public static function resetToDefaults(): void
    {
        static::truncate();
    }
}

