<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SupervisorController extends Controller
{
    /**
     * Tampilkan Dashboard Khusus Supervisor (Kasi Pelayanan - Verifikasi Kelayakan Faktual).
     */
    public function dashboard(): View
    {
        $user = Auth::user();

        // Statistik verifikasi faktual
        $stats = [
            'menunggu_verifikasi' => 5,
            'disetujui' => 41,
            'ditolak' => 3,
            'total_terverifikasi' => 44,
        ];

        // Data dummy antrian permohonan yang telah lolos administrasi staff
        $submissions = [
            [
                'no_tiket' => 'SKTM-2026-0018',
                'pemohon' => 'Bapak Warsito',
                'nik' => '3201011105700003',
                'tanggungan' => '5 Orang (Istri & 4 Anak)',
                'penghasilan' => 'Rp 950.000 / bulan (Buruh Tani)',
                'status_rumah' => 'Menumpang (Tanah Kas Desa)',
                'rekomendasi_staff' => 'Berkas lengkap dan sesuai KTP/KK',
                'status_verifikasi' => 'Menunggu Verifikasi',
            ],
            [
                'no_tiket' => 'SKTM-2026-0017',
                'pemohon' => 'Ibu Maryati (Janda)',
                'nik' => '3201014408750002',
                'tanggungan' => '3 Orang Anak Sekolah',
                'penghasilan' => 'Rp 600.000 / bulan (Penjual Jamu Keliling)',
                'status_rumah' => 'Milik Sendiri (Dinding semi permanen)',
                'rekomendasi_staff' => 'Berkas lengkap dan sesuai',
                'status_verifikasi' => 'Menunggu Verifikasi',
            ],
            [
                'no_tiket' => 'SKTM-2026-0016',
                'pemohon' => 'Deden Kurniawan',
                'nik' => '3201011802950005',
                'tanggungan' => '1 Orang',
                'penghasilan' => 'Rp 2.800.000 / bulan',
                'status_rumah' => 'Milik Sendiri',
                'rekomendasi_staff' => 'Berkas lengkap',
                'status_verifikasi' => 'Ditolak (Penghasilan Melebihi Kriteria Kemiskinan)',
            ],
        ];

        $notifications = [
            [
                'title' => 'Berkas Siap Diverifikasi',
                'message' => 'Staff telah menyelesaikan pemeriksaan 5 berkas dan meneruskannya ke meja verifikasi Anda.',
                'time' => '30 menit lalu',
                'type' => 'info',
            ],
            [
                'title' => 'Rekomendasi Diterima Kades',
                'message' => 'Seluruh rekomendasi kelayakan yang Anda ajukan kemarin telah disahkan oleh Kepala Desa.',
                'time' => '1 hari lalu',
                'type' => 'success',
            ],
        ];

        return view('dashboard.supervisor', compact('user', 'stats', 'submissions', 'notifications'));
    }
}
