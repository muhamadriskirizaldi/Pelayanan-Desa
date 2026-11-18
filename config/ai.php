<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default AI Document Checker Driver
    |--------------------------------------------------------------------------
    | Pilihan yang didukung:
    | - "mock"   : Simulasi pintar OCR dan verifikasi data tanpa ketergantungan API eksternal.
    | - "gemini" : Google Gemini Vision API (Multimodal OCR & Data Extraction).
    | - "openai" : OpenAI Vision API.
    */
    'driver' => env('AI_DOCUMENT_CHECKER_DRIVER', 'mock'),

    /*
    |--------------------------------------------------------------------------
    | Default AI Chat Assistant Driver
    |--------------------------------------------------------------------------
    | Pilihan yang didukung:
    | - "knowledge_base" / "mock" : Asisten cepat berbasis basis pengetahuan resmi desa.
    | - "gemini"                  : Google Gemini LLM API dengan panduan sistem ketat.
    */
    'chat_driver' => env('AI_CHAT_DRIVER', 'knowledge_base'),

    /*
    |--------------------------------------------------------------------------
    | API Key Provider AI
    |--------------------------------------------------------------------------
    | API Key WAJIB disimpan melalui Environment Variable dan TIDAK BOLEH
    | ditulis langsung (hardcoded) di dalam source code.
    */
    'api_key' => env('AI_API_KEY', env('GEMINI_API_KEY')),

    /*
    |--------------------------------------------------------------------------
    | Model AI yang Digunakan
    |--------------------------------------------------------------------------
    */
    'model' => env('AI_MODEL', 'gemini-1.5-flash'),

    /*
    |--------------------------------------------------------------------------
    | Batas Waktu Permintaan HTTP (Detik)
    |--------------------------------------------------------------------------
    */
    'timeout' => env('AI_REQUEST_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | Teks Disclaimer Resmi
    |--------------------------------------------------------------------------
    */
    'disclaimer' => 'AI hanya memberikan rekomendasi pemeriksaan. Keputusan akhir tetap dilakukan oleh petugas desa.',
];
