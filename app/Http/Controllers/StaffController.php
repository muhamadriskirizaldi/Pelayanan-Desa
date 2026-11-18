<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StaffController extends Controller
{
    /**
     * Tampilkan Dashboard Khusus Staff Pelayanan Desa (Pemeriksaan Administrasi).
     */
    public function dashboard(): View
    {
        $user = Auth::user();

        // Statistik ringkasan berkas administrasi
        $stats = [
            'pengajuan_baru' => 6,
            'perlu_pemeriksaan' => 9,
            'perlu_perbaikan' => 2,
            'selesai' => 48,
        ];

        // Data dummy antrian berkas administrasi masuk
        $submissions = [
            [
                'no_tiket' => 'SKTM-2026-0021',
                'pemohon' => 'Siti Nurhaliza',
                'nik' => '3201015509920002',
                'dusun' => 'Dusun Sukamaju RT 01 / RW 02',
                'keperluan' => 'Bantuan UKT Perguruan Tinggi',
                'tgl_masuk' => 'Hari ini, 08:30 WIB',
                'status_admin' => 'Perlu Pemeriksaan',
                'lampiran' => 'KTP, KK, Surat Pengantar RT/RW (Lengkap)',
            ],
            [
                'no_tiket' => 'SKTM-2026-0020',
                'pemohon' => 'Rahmat Hidayat',
                'nik' => '3201011204850004',
                'dusun' => 'Dusun Mekarwangi RT 03 / RW 04',
                'keperluan' => 'Keringanan Biaya Bersalin RSUD',
                'tgl_masuk' => 'Hari ini, 09:15 WIB',
                'status_admin' => 'Pengajuan Baru',
                'lampiran' => 'KTP, KK (Foto Pengantar RT blur)',
            ],
            [
                'no_tiket' => 'SKTM-2026-0019',
                'pemohon' => 'Ahmad Fauzi',
                'nik' => '3201012301980001',
                'dusun' => 'Dusun Karang Anyar RT 02 / RW 01',
                'keperluan' => 'Keringanan Pemasangan Listrik 450VA',
                'tgl_masuk' => 'Kemarin, 14:00 WIB',
                'status_admin' => 'Perlu Perbaikan',
                'lampiran' => 'Kartu Keluarga kadaluarsa',
            ],
        ];

        $notifications = [
            [
                'title' => '6 Pengajuan Baru Menunggu',
                'message' => 'Ada 6 pengajuan SKTM baru dari warga yang menunggu pemeriksaan kelengkapan berkas.',
                'time' => '10 menit lalu',
                'type' => 'info',
            ],
            [
                'title' => 'Catatan Perbaikan',
                'message' => 'Warga atas nama Ahmad Fauzi telah dikirimkan notifikasi untuk melengkapi foto KK terbaru.',
                'time' => '1 jam lalu',
                'type' => 'warning',
            ],
        ];

        return view('dashboard.staff', compact('user', 'stats', 'submissions', 'notifications'));
    }
}
