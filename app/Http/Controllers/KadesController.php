<?php

namespace App\Http\Controllers;

use App\Enums\SktmStatus;
use App\Models\SktmApplication;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class KadesController extends Controller
{
    /**
     * Tampilkan Dashboard Khusus Kepala Desa (Pusat Persetujuan Akhir / Approval Hub).
     */
    public function dashboard(): View
    {
        $user = Auth::user();

        $menungguCount = SktmApplication::whereIn('status', [
            SktmStatus::VERIFIED_BY_SUPERVISOR->value,
            SktmStatus::VERIFIED_SUPERVISOR->value,
        ])->count();

        $disetujuiCount = SktmApplication::whereIn('status', [
            SktmStatus::APPROVED->value,
            SktmStatus::APPROVED_KADES->value,
        ])->count();

        $ditolakCount = SktmApplication::where('status', SktmStatus::REJECTED->value)->count();
        $totalDecided = $disetujuiCount + $ditolakCount;
        $rasioPersetujuan = $totalDecided > 0 ? number_format(($disetujuiCount / $totalDecided) * 100, 1).'%' : '100%';

        // Statistik persetujuan & pelayanan desa
        $stats = [
            'menunggu_persetujuan' => $menungguCount,
            'disetujui' => $disetujuiCount,
            'ditolak' => $ditolakCount,
            'total_terbit' => $disetujuiCount,
            'rata_rata_waktu' => '1.5 Hari',
            'tingkat_persetujuan' => $rasioPersetujuan,
        ];

        // Daftar permohonan riil yang menunggu persetujuan akhir Kepala Desa
        $pendingApplications = SktmApplication::with(['statusHistories.user'])
            ->whereIn('status', [
                SktmStatus::VERIFIED_BY_SUPERVISOR->value,
                SktmStatus::VERIFIED_SUPERVISOR->value,
            ])
            ->latest('updated_at')
            ->take(5)
            ->get();

        $notifications = [
            [
                'title' => $menungguCount > 0 ? "{$menungguCount} Berkas Siap Anda Sahkan" : 'Tidak ada berkas tertunda',
                'message' => $menungguCount > 0
                    ? "Terdapat {$menungguCount} permohonan SKTM terverifikasi yang memerlukan persetujuan akhir Anda."
                    : 'Semua permohonan yang diverifikasi telah diproses.',
                'time' => 'Terkini',
                'type' => 'warning',
            ],
            [
                'title' => 'Laporan Bulanan Pelayanan',
                'message' => 'Rekapitulasi pelayanan surat desa bulan berjalan siap ditinjau.',
                'time' => '1 hari lalu',
                'type' => 'info',
            ],
        ];

        return view('dashboard.kades', compact('user', 'stats', 'pendingApplications', 'notifications'));
    }
}
