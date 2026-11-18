<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Identitas Pemerintahan Desa (Kop Surat & Metadata)
    |--------------------------------------------------------------------------
    |
    | Konfigurasi ini digunakan sebagai kop surat resmi dan atribut penerbitan
    | dokumen pelayanan desa. Seluruh nilai dapat dikustomisasi melalui file .env.
    |
    */
    'nama_desa' => env('VILLAGE_NAME', 'Sukamaju'),
    'nama_kecamatan' => env('VILLAGE_KECAMATAN', 'Ciomas'),
    'nama_kabupaten' => env('VILLAGE_KABUPATEN', 'Kabupaten Bogor'),
    'nama_provinsi' => env('VILLAGE_PROVINSI', 'Jawa Barat'),
    'kode_pos' => env('VILLAGE_POSTAL_CODE', '16610'),
    'alamat_kantor' => env('VILLAGE_ADDRESS', 'Jl. Raya Sukamaju No. 123'),
    'telepon' => env('VILLAGE_PHONE', '(0251) 863xxxx'),
    'email' => env('VILLAGE_EMAIL', 'desasukamaju@bogorkab.go.id'),
    'website' => env('VILLAGE_WEBSITE', 'sukamaju.desa.id'),

    /*
    |--------------------------------------------------------------------------
    | Pejabat Kepala Desa Penandatangan Surat
    |--------------------------------------------------------------------------
    */
    'kepala_desa' => [
        'nama' => env('VILLAGE_KADES_NAME', 'H. Suryono, S.Sos.'),
        'jabatan' => env('VILLAGE_KADES_TITLE', 'Kepala Desa Sukamaju'),
        'nip' => env('VILLAGE_KADES_NIP', '19750812 200212 1 003'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi Khusus Surat Keterangan Tidak Mampu (SKTM)
    |--------------------------------------------------------------------------
    */
    'sktm' => [
        'kode_surat' => env('SKTM_KODE_SURAT', '470'),
        'judul_surat' => 'SURAT KETERANGAN TIDAK MAMPU',
        'klasifikasi' => 'Pelayanan Kesejahteraan Sosial',
    ],
];
