<?php

namespace App\Http\Controllers;

use App\Enums\SktmStatus;
use App\Models\SktmApplication;
use App\Services\SktmDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KadesSktmController extends Controller
{
    /**
     * Tampilkan Daftar Pengajuan SKTM yang Siap Disetujui Kepala Desa.
     * Secara default dan utama hanya pengajuan yang berstatus VERIFIED_BY_SUPERVISOR.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        abort_unless($user && $user->isKepalaDesa(), 403, 'Akses Ditolak: Halaman ini khusus untuk Kepala Desa.');

        $statusFilter = $request->query('status');
        $search = trim($request->input('search', $request->input('q', '')));

        $query = SktmApplication::with(['user', 'statusHistories']);

        // Default: Pengajuan yang masuk ke Kepala Desa hanya yang berstatus VERIFIED_BY_SUPERVISOR
        if ($statusFilter && $statusFilter !== 'all') {
            $normalizedStatus = strtolower($statusFilter);
            if ($normalizedStatus === 'approved') {
                $query->whereIn('status', [SktmStatus::APPROVED->value, SktmStatus::APPROVED_KADES->value]);
            } else {
                $query->where('status', $normalizedStatus);
            }
        } else {
            $query->whereIn('status', [
                SktmStatus::VERIFIED_BY_SUPERVISOR->value,
                SktmStatus::VERIFIED_SUPERVISOR->value,
            ]);
        }

        // Pencarian berdasarkan nama pemohon, NIK, atau nomor pengajuan
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nama_pemohon', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%")
                    ->orWhere('nomor_pengajuan', 'like', "%{$search}%")
                    ->orWhere('nomor_surat', 'like', "%{$search}%");
            });
        }

        $applications = $query->latest('updated_at')->paginate(10)->withQueryString();

        // Statistik ringkasan persetujuan Kepala Desa
        $stats = [
            'menunggu_persetujuan' => SktmApplication::whereIn('status', [
                SktmStatus::VERIFIED_BY_SUPERVISOR->value,
                SktmStatus::VERIFIED_SUPERVISOR->value,
            ])->count(),
            'disetujui' => SktmApplication::whereIn('status', [
                SktmStatus::APPROVED->value,
                SktmStatus::APPROVED_KADES->value,
            ])->count(),
            'ditolak' => SktmApplication::where('status', SktmStatus::REJECTED->value)->count(),
            'total' => SktmApplication::count(),
        ];

        return view('kades.sktm.index', compact('applications', 'stats', 'statusFilter', 'search'));
    }

    /**
     * Tampilkan Detail Lengkap Pemohon, Dokumen, Catatan Staff, dan Catatan Supervisor.
     */
    public function show(SktmApplication $sktm): View
    {
        $user = Auth::user();
        abort_unless($user && $user->isKepalaDesa(), 403, 'Akses Ditolak: Halaman ini khusus untuk Kepala Desa.');

        abort_if(
            in_array($sktm->status, [
                SktmStatus::DRAFT,
                SktmStatus::SUBMITTED,
                SktmStatus::UNDER_REVIEW,
                SktmStatus::STAFF_REVIEW,
                SktmStatus::VERIFIED_BY_STAFF,
            ], true),
            403,
            'Akses Ditolak: Permohonan ini belum melalui tahapan verifikasi Supervisor.'
        );

        $sktm->load(['user.residentProfile', 'statusHistories.user']);
        $latestStaffLog = $sktm->latestStaffVerification();
        $latestSupervisorLog = $sktm->latestSupervisorVerification();
        $latestKadesLog = $sktm->latestKadesApproval();

        return view('kades.sktm.show', compact('sktm', 'latestStaffLog', 'latestSupervisorLog', 'latestKadesLog'));
    }

    /**
     * Menyetujui Pengajuan SKTM (VERIFIED_BY_SUPERVISOR -> APPROVED).
     * Otomatis membuat nomor surat, menghasilkan PDF dokumen resmi, menandai siap terbit, dan mencatat audit log.
     */
    public function approve(Request $request, SktmApplication $sktm, SktmDocumentService $documentService): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->isKepalaDesa(), 403, 'Akses Ditolak: Tindakan persetujuan akhir khusus untuk Kepala Desa.');

        // Hanya pengajuan berstatus VERIFIED_BY_SUPERVISOR yang dapat disetujui Kepala Desa
        if (! $sktm->isVerifiedBySupervisor()) {
            return back()->with('error', 'Hanya permohonan yang telah diverifikasi oleh Supervisor (VERIFIED_BY_SUPERVISOR) yang dapat disetujui oleh Kepala Desa.');
        }

        $request->validate([
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $catatan = $request->input('catatan') ?: 'Permohonan SKTM telah disetujui oleh Kepala Desa. Nomor surat resmi telah diterbitkan.';

        $sktm->recordStatusTransition(
            user: $user,
            newStatus: SktmStatus::APPROVED,
            notes: $catatan
        );

        // Generate dokumen PDF SKTM resmi dan simpan metadata
        $documentService->generatePdf($sktm);

        return redirect()->route('kades.sktm.show', $sktm->id)
            ->with('status', 'Permohonan SKTM berhasil disetujui. Dokumen PDF resmi dan nomor surat telah diterbitkan: '.$sktm->fresh()->nomor_surat.'. Siap diunduh/dicetak.');
    }

    /**
     * Menolak Pengajuan SKTM (VERIFIED_BY_SUPERVISOR -> REJECTED).
     * Wajib memasukkan alasan penolakan dan mencatat audit log.
     */
    public function reject(Request $request, SktmApplication $sktm): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->isKepalaDesa(), 403, 'Akses Ditolak: Tindakan penolakan khusus untuk Kepala Desa.');

        // Hanya pengajuan berstatus VERIFIED_BY_SUPERVISOR yang dapat diputuskan oleh Kepala Desa
        if (! $sktm->isVerifiedBySupervisor()) {
            return back()->with('error', 'Status permohonan saat ini bukan VERIFIED_BY_SUPERVISOR sehingga tidak dapat diputuskan oleh Kepala Desa.');
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

        return redirect()->route('kades.sktm.show', $sktm->id)
            ->with('status', 'Permohonan SKTM telah ditolak oleh Kepala Desa.');
    }

    /**
     * Membatalkan persetujuan SKTM yang telah disetujui Kepala Desa (APPROVED -> REJECTED).
     * Wajib meminta alasan pembatalan, menyimpan pada catatan pengajuan,
     * mencatat audit history, dan mengirim notifikasi status ke pemohon.
     */
    public function cancelApproval(Request $request, SktmApplication $sktm): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->isKepalaDesa(), 403, 'Akses Ditolak: Pembatalan persetujuan khusus untuk Kepala Desa.');

        if (! $sktm->isApproved()) {
            return back()->with('error', 'Hanya permohonan yang telah disetujui (APPROVED) yang dapat dibatalkan persetujuannya.');
        }

        $request->validate([
            'alasan_pembatalan' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'alasan_pembatalan.required' => 'Alasan pembatalan persetujuan wajib diisi.',
            'alasan_pembatalan.min' => 'Alasan pembatalan minimal 5 karakter.',
        ]);

        $alasan = 'Persetujuan dibatalkan oleh Kepala Desa: ' . trim($request->input('alasan_pembatalan'));
        $sktm->is_ready_for_issuance = false;

        $sktm->recordStatusTransition(
            user: $user,
            newStatus: SktmStatus::REJECTED,
            notes: $alasan
        );

        return redirect()->route('kades.sktm.show', $sktm->id)
            ->with('status', 'Persetujuan SKTM berhasil dibatalkan dan alasan telah dicatat dalam riwayat audit.');
    }

    /**
     * Unduh / Buka Dokumen Persyaratan secara Aman untuk Kepala Desa.
     */
    public function downloadDocument(SktmApplication $sktm, string $type): StreamedResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->isKepalaDesa(), 403, 'Akses Ditolak: Dokumen ini hanya dapat diakses oleh Kepala Desa.');

        abort_if(
            in_array($sktm->status, [
                SktmStatus::DRAFT,
                SktmStatus::SUBMITTED,
                SktmStatus::UNDER_REVIEW,
                SktmStatus::STAFF_REVIEW,
                SktmStatus::VERIFIED_BY_STAFF,
            ], true),
            403,
            'Akses Ditolak: Dokumen ini belum masuk kewenangan Kepala Desa.'
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

    /**
     * Unduh Berkas PDF SKTM Resmi yang telah Diterbitkan.
     */
    public function downloadPdf(SktmApplication $sktm, SktmDocumentService $documentService): StreamedResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->isKepalaDesa(), 403, 'Akses Ditolak: Dokumen ini hanya dapat diakses oleh Kepala Desa.');

        return $documentService->downloadPdf($sktm, $user);
    }

    /**
     * Pratinjau / Generate Tampilan Surat SKTM Resmi Siap Cetak/PDF.
     * Hanya dapat diakses untuk pengajuan yang telah berstatus APPROVED.
     */
    public function previewPdf(SktmApplication $sktm): View
    {
        $user = Auth::user();
        abort_unless($user && $user->isKepalaDesa(), 403, 'Akses Ditolak: Surat hanya dapat dilihat oleh pejabat yang berwenang.');

        if (! $sktm->isApproved()) {
            abort(400, 'Surat keterangan belum dapat diterbitkan karena belum disetujui secara resmi oleh Kepala Desa.');
        }

        $sktm->load(['user.residentProfile', 'statusHistories.user']);
        $latestKadesLog = $sktm->latestKadesApproval();

        return view('kades.sktm.preview-pdf', compact('sktm', 'latestKadesLog'));
    }
}
