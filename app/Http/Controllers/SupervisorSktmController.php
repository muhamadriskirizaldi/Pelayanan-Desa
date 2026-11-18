<?php

namespace App\Http\Controllers;

use App\Enums\SktmStatus;
use App\Models\SktmApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupervisorSktmController extends Controller
{
    /**
     * Tampilkan Daftar Pengajuan SKTM untuk Verifikasi Faktual Supervisor.
     * Hanya pengajuan yang berstatus VERIFIED_BY_STAFF yang masuk antrian utama.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        abort_unless($user && $user->isSupervisor(), 403, 'Akses Ditolak: Halaman ini khusus untuk Supervisor.');

        $statusFilter = $request->query('status');
        $search = trim($request->input('search', $request->input('q', '')));

        $query = SktmApplication::with(['user', 'statusHistories']);

        // Default: Pengajuan yang muncul di Supervisor hanya yang berstatus VERIFIED_BY_STAFF
        if ($statusFilter && $statusFilter !== 'all') {
            $normalizedStatus = strtolower($statusFilter);
            $query->where('status', $normalizedStatus);
        } else {
            $query->where('status', SktmStatus::VERIFIED_BY_STAFF->value);
        }

        // Pencarian berdasarkan nama pemohon, NIK, atau nomor pengajuan
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nama_pemohon', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%")
                    ->orWhere('nomor_pengajuan', 'like', "%{$search}%");
            });
        }

        $applications = $query->latest('updated_at')->paginate(10)->withQueryString();

        // Statistik ringkasan pengajuan terkait supervisor
        $stats = [
            'menunggu_verifikasi' => SktmApplication::where('status', SktmStatus::VERIFIED_BY_STAFF->value)->count(),
            'disetujui_supervisor' => SktmApplication::whereIn('status', [
                SktmStatus::VERIFIED_BY_SUPERVISOR->value,
                SktmStatus::VERIFIED_SUPERVISOR->value,
            ])->count(),
            'perlu_perbaikan' => SktmApplication::where('status', SktmStatus::NEED_REVISION->value)->count(),
            'ditolak' => SktmApplication::where('status', SktmStatus::REJECTED->value)->count(),
        ];

        return view('supervisor.sktm.index', compact('applications', 'stats', 'statusFilter', 'search'));
    }

    /**
     * Tampilkan Detail Permohonan & Hasil Pemeriksaan Staff untuk Verifikasi Kelayakan.
     */
    public function show(SktmApplication $sktm): View
    {
        $user = Auth::user();
        abort_unless($user && $user->isSupervisor(), 403, 'Akses Ditolak: Halaman ini khusus untuk Supervisor.');

        abort_if(
            in_array($sktm->status, [
                SktmStatus::DRAFT,
                SktmStatus::SUBMITTED,
                SktmStatus::UNDER_REVIEW,
                SktmStatus::STAFF_REVIEW,
            ], true),
            403,
            'Akses Ditolak: Permohonan ini belum melalui tahapan verifikasi Staff.'
        );

        $sktm->load(['user.residentProfile', 'statusHistories.user']);
        $latestStaffLog = $sktm->latestStaffVerification();

        return view('supervisor.sktm.show', compact('sktm', 'latestStaffLog'));
    }

    /**
     * Menyetujui Verifikasi Kelayakan dan Meneruskan ke Kepala Desa (VERIFIED_BY_STAFF -> VERIFIED_BY_SUPERVISOR).
     */
    public function verify(Request $request, SktmApplication $sktm): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->isSupervisor(), 403, 'Akses Ditolak: Tindakan ini khusus untuk Supervisor.');

        // Hanya pengajuan berstatus VERIFIED_BY_STAFF yang dapat diverifikasi oleh Supervisor
        if ($sktm->status !== SktmStatus::VERIFIED_BY_STAFF) {
            return back()->with('error', 'Status permohonan saat ini bukan VERIFIED_BY_STAFF sehingga tidak dapat diverifikasi oleh Supervisor.');
        }

        $request->validate([
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $catatan = $request->input('catatan') ?: 'Verifikasi faktual kelayakan disetujui oleh Supervisor. Pengajuan diteruskan ke Kepala Desa untuk persetujuan akhir.';

        $sktm->recordStatusTransition(
            user: $user,
            newStatus: SktmStatus::VERIFIED_BY_SUPERVISOR,
            notes: $catatan
        );

        return redirect()->route('supervisor.sktm.show', $sktm->id)
            ->with('status', 'Verifikasi kelayakan berhasil disetujui. Pengajuan diteruskan ke Kepala Desa.');
    }

    /**
     * Menolak Pengajuan SKTM (VERIFIED_BY_STAFF -> REJECTED).
     */
    public function reject(Request $request, SktmApplication $sktm): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->isSupervisor(), 403, 'Akses Ditolak: Tindakan ini khusus untuk Supervisor.');

        // Hanya pengajuan berstatus VERIFIED_BY_STAFF yang dapat ditolak oleh Supervisor
        if ($sktm->status !== SktmStatus::VERIFIED_BY_STAFF) {
            return back()->with('error', 'Status permohonan saat ini bukan VERIFIED_BY_STAFF sehingga tidak dapat ditolak oleh Supervisor.');
        }

        $request->validate([
            'catatan' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'catatan.required' => 'Alasan penolakan permohonan wajib diisi.',
            'catatan.min' => 'Alasan penolakan minimal 5 karakter.',
        ]);

        $sktm->recordStatusTransition(
            user: $user,
            newStatus: SktmStatus::REJECTED,
            notes: $request->input('catatan')
        );

        return redirect()->route('supervisor.sktm.show', $sktm->id)
            ->with('status', 'Permohonan SKTM telah ditolak oleh Supervisor.');
    }

    /**
     * Mengembalikan Pengajuan untuk Perbaikan jika Diperlukan (VERIFIED_BY_STAFF -> NEED_REVISION).
     */
    public function requestRevision(Request $request, SktmApplication $sktm): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->isSupervisor(), 403, 'Akses Ditolak: Tindakan ini khusus untuk Supervisor.');

        // Hanya pengajuan berstatus VERIFIED_BY_STAFF yang dapat dikembalikan untuk perbaikan oleh Supervisor
        if ($sktm->status !== SktmStatus::VERIFIED_BY_STAFF) {
            return back()->with('error', 'Status permohonan saat ini bukan VERIFIED_BY_STAFF sehingga tidak dapat dikembalikan untuk perbaikan.');
        }

        $request->validate([
            'catatan' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'catatan.required' => 'Alasan pengembalian berkas untuk perbaikan wajib diisi.',
            'catatan.min' => 'Alasan perbaikan minimal 5 karakter.',
        ]);

        $sktm->recordStatusTransition(
            user: $user,
            newStatus: SktmStatus::NEED_REVISION,
            notes: $request->input('catatan')
        );

        return redirect()->route('supervisor.sktm.show', $sktm->id)
            ->with('status', 'Permohonan berhasil dikembalikan ke pemohon untuk perbaikan berkas/data.');
    }

    /**
     * Unduh / Buka Dokumen Persyaratan secara Aman untuk Supervisor.
     */
    public function downloadDocument(SktmApplication $sktm, string $type): StreamedResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->isSupervisor(), 403, 'Akses Ditolak: Dokumen ini hanya dapat diakses oleh petugas berwenang.');

        abort_if(
            in_array($sktm->status, [
                SktmStatus::DRAFT,
                SktmStatus::SUBMITTED,
                SktmStatus::UNDER_REVIEW,
                SktmStatus::STAFF_REVIEW,
            ], true),
            403,
            'Akses Ditolak: Dokumen ini belum masuk kewenangan verifikasi Supervisor.'
        );

        $path = match ($type) {
            'ktp' => $sktm->dokumen_ktp_path,
            'kk' => $sktm->dokumen_kk_path,
            'pendukung' => $sktm->dokumen_pendukung_path,
            default => null,
        };

        $originalName = match ($type) {
            'ktp' => $sktm->dokumen_ktp_original_name ?? 'Dokumen_KTP.pdf',
            'kk' => $sktm->dokumen_kk_original_name ?? 'Dokumen_KK.pdf',
            'pendukung' => $sktm->dokumen_pendukung_original_name ?? 'Dokumen_Pendukung.pdf',
            default => 'dokumen',
        };

        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404, 'Berkas dokumen fisik tidak ditemukan di sistem penyimpanan server.');
        }

        $safeDownloadName = preg_replace('/[^\w\s\.-]/', '_', basename($originalName));
        $safeDownloadName = preg_replace('/\s+/', ' ', trim($safeDownloadName));
        if ($safeDownloadName === '' || $safeDownloadName === '.') {
            $safeDownloadName = 'dokumen.pdf';
        }

        return Storage::disk('local')->response(
            $path,
            $safeDownloadName,
            [
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-cache, must-revalidate',
            ]
        );
    }
}
