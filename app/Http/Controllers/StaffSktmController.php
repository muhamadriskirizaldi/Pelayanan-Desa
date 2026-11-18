<?php

namespace App\Http\Controllers;

use App\Enums\SktmStatus;
use App\Models\SktmApplication;
use App\Services\Ai\AiDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffSktmController extends Controller
{
    /**
     * Tampilkan Daftar Pengajuan SKTM untuk Diperiksa oleh Staff.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        abort_unless($user && $user->isStaff(), 403, 'Akses Ditolak: Halaman ini khusus untuk Staff Pelayanan Desa.');

        $statusFilter = $request->query('status');
        $search = trim($request->input('search', $request->input('q', '')));

        // Query pengajuan - Staff memeriksa pengajuan yang sudah diajukan warga (bukan draf)
        $query = SktmApplication::with('user');

        // Filter status jika dipilih
        if ($statusFilter && $statusFilter !== 'all') {
            $normalizedStatus = strtolower($statusFilter);
            $query->where('status', $normalizedStatus);
        } else {
            // Sembunyikan draf murni dari antrian default pemeriksaan staff
            $query->where('status', '!=', SktmStatus::DRAFT->value);
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

        // Hitung statistik antrian untuk ringkasan cepat
        $stats = [
            'total' => SktmApplication::where('status', '!=', SktmStatus::DRAFT->value)->count(),
            'submitted' => SktmApplication::where('status', SktmStatus::SUBMITTED->value)->count(),
            'under_review' => SktmApplication::whereIn('status', [
                SktmStatus::UNDER_REVIEW->value,
                SktmStatus::STAFF_REVIEW->value,
            ])->count(),
            'need_revision' => SktmApplication::where('status', SktmStatus::NEED_REVISION->value)->count(),
            'verified_by_staff' => SktmApplication::where('status', SktmStatus::VERIFIED_BY_STAFF->value)->count(),
        ];

        return view('staff.sktm.index', compact('applications', 'stats', 'statusFilter', 'search'));
    }

    /**
     * Tampilkan Rincian Detail Pengajuan SKTM untuk Pemeriksaan Berkas.
     */
    public function show(SktmApplication $sktm, AiDocumentService $aiService): View
    {
        $user = Auth::user();
        abort_unless($user && $user->isStaff(), 403, 'Akses Ditolak: Halaman ini khusus untuk Staff Pelayanan Desa.');

        // Draf milik warga belum diajukan secara resmi dan tidak boleh diakses oleh Staff
        abort_if(
            $sktm->isDraft(),
            403,
            'Akses Ditolak: Permohonan ini masih berupa draf dan belum diajukan oleh pemohon.'
        );

        // Transisi otomatis: saat Staff pertama kali membuka detail pengajuan yang masih SUBMITTED,
        // status berpindah menjadi UNDER_REVIEW dan dicatat dalam audit log.
        if ($sktm->status === SktmStatus::SUBMITTED) {
            $sktm->recordStatusTransition(
                user: $user,
                newStatus: SktmStatus::UNDER_REVIEW,
                notes: 'Staff membuka berkas permohonan untuk memulai pemeriksaan administrasi.'
            );
            $sktm->refresh();
        }

        $sktm->load(['user.residentProfile', 'statusHistories.user']);

        // Jalankan atau ambil hasil AI Document Check
        $aiResult = $aiService->checkApplication($sktm);

        return view('staff.sktm.show', compact('sktm', 'aiResult'));
    }

    /**
     * Jalankan Ulang Pemeriksaan Berkas Dokumen dengan AI (OCR & Analisis Identitas).
     */
    public function runAiCheck(Request $request, SktmApplication $sktm, AiDocumentService $aiService): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->isStaff(), 403, 'Akses Ditolak: Tindakan ini khusus untuk Staff Pelayanan Desa.');

        $aiService->checkApplication($sktm, force: true);

        return redirect()->route('staff.sktm.show', $sktm->id)
            ->with('status', 'Pemeriksaan dokumen oleh AI berhasil diperbarui. Silakan tinjau rekomendasi di bawah.');
    }

    /**
     * Aksi Eksplisit Mulai Pemeriksaan Berkas (SUBMITTED -> UNDER_REVIEW).
     */
    public function startReview(Request $request, SktmApplication $sktm): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->isStaff(), 403, 'Akses Ditolak: Tindakan ini khusus untuk Staff Pelayanan Desa.');

        if ($sktm->status === SktmStatus::SUBMITTED) {
            $notes = $request->input('catatan', 'Staff memulai proses pemeriksaan berkas administrasi.');
            $sktm->recordStatusTransition(
                user: $user,
                newStatus: SktmStatus::UNDER_REVIEW,
                notes: $notes
            );
        }

        return redirect()->route('staff.sktm.show', $sktm->id)
            ->with('status', 'Pemeriksaan berkas dimulai. Status pengajuan kini: Sedang Diperiksa (UNDER_REVIEW).');
    }

    /**
     * Aksi Meminta Perbaikan Berkas / Data ke Masyarakat (UNDER_REVIEW / SUBMITTED -> NEED_REVISION).
     */
    public function requestRevision(Request $request, SktmApplication $sktm): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->isStaff(), 403, 'Akses Ditolak: Tindakan ini khusus untuk Staff Pelayanan Desa.');

        $request->validate([
            'catatan' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'catatan.required' => 'Alasan permintaan perbaikan wajib diisi agar pemohon mengetahui hal yang perlu diperbaiki.',
            'catatan.min' => 'Alasan perbaikan minimal 5 karakter.',
        ]);

        if (! in_array($sktm->status, [SktmStatus::SUBMITTED, SktmStatus::UNDER_REVIEW, SktmStatus::STAFF_REVIEW], true)) {
            return back()->with('error', 'Status permohonan saat ini tidak dapat diminta perbaikan.');
        }

        $sktm->recordStatusTransition(
            user: $user,
            newStatus: SktmStatus::NEED_REVISION,
            notes: $request->input('catatan')
        );

        return redirect()->route('staff.sktm.show', $sktm->id)
            ->with('status', 'Permintaan perbaikan berkas berhasil dikirimkan ke pemohon.');
    }

    /**
     * Aksi Verifikasi Berkas oleh Staff dan Meneruskan ke Supervisor (UNDER_REVIEW / SUBMITTED -> VERIFIED_BY_STAFF).
     */
    public function verify(Request $request, SktmApplication $sktm): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->isStaff(), 403, 'Akses Ditolak: Tindakan ini khusus untuk Staff Pelayanan Desa.');

        $request->validate([
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! in_array($sktm->status, [SktmStatus::SUBMITTED, SktmStatus::UNDER_REVIEW, SktmStatus::STAFF_REVIEW], true)) {
            return back()->with('error', 'Status permohonan saat ini tidak dapat diverifikasi oleh Staff.');
        }

        $catatan = $request->input('catatan') ?: 'Berkas administrasi lengkap dan valid. Diteruskan ke Supervisor untuk verifikasi faktual/kelayakan.';

        $sktm->recordStatusTransition(
            user: $user,
            newStatus: SktmStatus::VERIFIED_BY_STAFF,
            notes: $catatan
        );

        return redirect()->route('staff.sktm.show', $sktm->id)
            ->with('status', 'Pengajuan berhasil diverifikasi oleh Staff dan diteruskan ke Supervisor.');
    }

    /**
     * Unduh / Buka Dokumen Persyaratan secara Aman untuk Staff.
     */
    public function downloadDocument(SktmApplication $sktm, string $type): StreamedResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->isStaff(), 403, 'Akses Ditolak: Dokumen ini hanya dapat diakses oleh petugas berwenang.');

        abort_if(
            $sktm->isDraft(),
            403,
            'Akses Ditolak: Dokumen pada permohonan draf belum diajukan secara resmi.'
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
