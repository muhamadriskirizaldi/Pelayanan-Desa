<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppSetting extends Model
{
    protected $table = 'whatsapp_settings';

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Ambil nilai pengaturan dengan fallback ke config atau default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $setting = static::where('key', $key)->first();
            if ($setting !== null && $setting->value !== null) {
                return $setting->value;
            }
        } catch (\Throwable $e) {
            // Fallback jika tabel belum termigrasi
        }

        return config("whatsapp.{$key}", $default);
    }

    /**
     * Simpan atau update nilai pengaturan.
     */
    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]
        );
    }

    /**
     * Cek apakah notifikasi WhatsApp aktif secara global.
     */
    public static function isEnabled(): bool
    {
        $val = static::get('enabled', config('whatsapp.enabled', false));
        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }
}

