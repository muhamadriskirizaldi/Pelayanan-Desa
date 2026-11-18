<?php

namespace App\Http\Controllers;

use App\Enums\SktmStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MasyarakatController extends Controller
{
    /**
     * Tampilkan Dashboard Khusus Masyarakat (Pemohon Layanan).
     */
    public function dashboard(): View
    {
        $user = Auth::user();
        $user->load('residentProfile');

        // Ambil pengajuan SKTM nyata milik pengguna
        $submissions = $user->sktmApplications()->latest()->take(5)->get();

        $stats = [
            'total' => $user->sktmApplications()->count(),
            'draft' => $user->sktmApplications()->where('status', SktmStatus::DRAFT)->count(),
            'diproses' => $user->sktmApplications()->whereNotIn('status', [
                SktmStatus::DRAFT,
                SktmStatus::APPROVED,
                SktmStatus::APPROVED_KADES,
                SktmStatus::REJECTED,
            ])->count(),
            'selesai' => $user->sktmApplications()->whereIn('status', [
                SktmStatus::APPROVED,
                SktmStatus::APPROVED_KADES,
            ])->count(),
        ];

        // Notifikasi kontekstual untuk warga
        $notifications = [];
        $latestSubmitted = $user->sktmApplications()->where('status', SktmStatus::SUBMITTED)->latest()->first();
        if ($latestSubmitted) {
            $notifications[] = [
                'title' => 'Berkas Dalam Antrian Petugas',
                'message' => "Permohonan ({$latestSubmitted->nomor_pengajuan}) telah terkirim dan sedang menunggu pemeriksaan petugas desa.",
                'time' => $latestSubmitted->created_at->diffForHumans(),
                'type' => 'info',
            ];
        }

        $latestDraft = $user->sktmApplications()->where('status', SktmStatus::DRAFT)->latest()->first();
        if ($latestDraft) {
            $notifications[] = [
                'title' => 'Draf Belum Terkirim',
                'message' => "Anda memiliki draf pengajuan ({$latestDraft->nomor_pengajuan}) yang belum dikirim ke kantor desa.",
                'time' => $latestDraft->created_at->diffForHumans(),
                'type' => 'warning',
            ];
        }

        if (empty($notifications)) {
            $notifications[] = [
                'title' => 'Selamat Datang di SIPELDES',
                'message' => 'Layanan permohonan SKTM online kini telah aktif dan siap digunakan.',
                'time' => 'Informasi',
                'type' => 'info',
            ];
        }

        return view('dashboard.masyarakat', compact('user', 'submissions', 'stats', 'notifications'));
    }
}
