<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Status Aktif WhatsApp Gateway
    |--------------------------------------------------------------------------
    */
    'enabled' => env('FONTE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi FONTE API (Fonnte WhatsApp Gateway)
    |--------------------------------------------------------------------------
    */
    'api_url' => env('FONTE_API_URL', 'https://api.fonnte.com/send'),
    'device_check_url' => env('FONTE_DEVICE_CHECK_URL', 'https://api.fonnte.com/device'),
    'api_token' => env('FONTE_API_TOKEN', ''),
    'device_id' => env('FONTE_DEVICE_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Default Timeout Request (Detik)
    |--------------------------------------------------------------------------
    */
    'timeout' => 12,

    /*
    |--------------------------------------------------------------------------
    | Pengaturan Tipe Notifikasi yang Diaktifkan
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'submitted' => true,
        'verified_staff' => true,
        'need_revision' => true,
        'verified_supervisor' => true,
        'approved' => true,
        'rejected' => true,
        'document_ready' => true,
    ],
];

