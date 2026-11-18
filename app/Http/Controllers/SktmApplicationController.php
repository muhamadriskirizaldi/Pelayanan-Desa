<?php

namespace App\Http\Controllers;

use App\Enums\SktmStatus;
use App\Http\Requests\Sktm\StoreSktmApplicationRequest;
use App\Models\SktmApplication;
use App\Services\NotificationService;
use App\Services\SktmDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SktmApplicationController extends Controller
{
    /**
     * Tampilkan Riwayat dan Daftar Pengajuan SKTM milik Warga.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();

        $statusFilter = $request->query('status');
        $query = $user->sktmApplications()->latest();

        if ($statusFilter && $statusFilter !== 'all') {
            if ($statusFilter === 'diproses') {
                $query->whereNotIn('status', [
                    SktmStatus::DRAFT->value,
                    SktmStatus::APPROVED->value,
                    SktmStatus::APPROVED_KADES->value,
                    SktmStatus::REJECTED->value,
                ]);
            } elseif ($statusFilter === 'selesai' || $statusFilter === 'approved') {
                $query->whereIn('status', [
                    SktmStatus::APPROVED->value,
                    SktmStatus::APPROVED_KADES->value,
                ]);
            } elseif (in_array($statusFilter, SktmStatus::values(), true)) {
                $query->where('status', $statusFilter);
            }
        }

        $applications = $query->paginate(10)->withQueryString();

        // Statistik ringkasan pemohon (mendukung seluruh variasi status)
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

        return view('sktm.index', compact('applications', 'stats', 'statusFilter'));
    }

    /**
     * Tampilkan Formulir Pengajuan SKTM Baru.
     */
    public function create(): View
    {
        $user = Auth::user();
        $user->load('residentProfile');

        $profile = $user->residentProfile;

        // Opsi keperluan umum SKTM
        $commonPurposes = [
            'Keringanan Biaya Pendidikan / Beasiswa Sekolah & Kuliah',
            'Pendaftaran BPJS PBI (Bantuan Iuran Jaminan Kesehatan)',
            'Keringanan Biaya Pengobatan Rawat Inap RSUD',
            'Bantuan Pasang Baru Listrik Bersubsidi PLN 450VA',
            'Bantuan Permohonan Bantuan Hukum / Advokasi Pro Bono',
            'Bantuan Sosial Pangan & PKH',
            'Lainnya',
        ];

        return view('sktm.create', compact('user', 'profile', 'commonPurposes'));
    }

    /**
     * Simpan Pengajuan Baru (Draf atau Submit Resmi).
     */
    public function store(StoreSktmApplicationRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $user->load('residentProfile');
        $profile = $user->residentProfile;

        $action = $request->input('action', 'submit');
        $isSubmitting = ($action === 'submit');

        // Cegah pengiriman ganda (Double submission prevention)
        $recentDuplicate = SktmApplication::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subSeconds(4))
            ->where('keperluan', $request->input('keperluan'))
            ->first();

        if ($recentDuplicate) {
            return redirect()->route('warga.sktm.show', $recentDuplicate->id)
                ->with('status', "Permohonan SKTM Anda telah berhasil tersimpan dengan Nomor Pengajuan: {$recentDuplicate->nomor_pengajuan}.");
        }

        // 1. Upload dokumen secara aman ke Private Disk
        $ktpData = $this->handleSecureUpload($request, 'dokumen_ktp');
        $kkData = $this->handleSecureUpload($request, 'dokumen_kk');
        $pendukungData = $this->handleSecureUpload($request, 'dokumen_pendukung');

        // 2. Eksekusi penyimpanan atomik dalam database transaction
        $application = DB::transaction(function () use ($user, $profile, $request, $action, $isSubmitting, $ktpData, $kkData, $pendukungData) {
            $nomorPengajuan = SktmApplication::generateNomorPengajuan();

            $application = new SktmApplication;
            $application->nomor_pengajuan = $nomorPengajuan;
            $application->user_id = $user->id;

            // Snapshot identitas kependudukan
            $application->nama_pemohon = $user->name;
            $application->nik = $profile?->nik ?? $request->input('nik', '3201010000000001');
            $application->no_kk = $profile?->no_kk ?? $request->input('no_kk', '3201010000000002');
            $application->alamat = $request->input('alamat', $profile?->alamat ?? 'Alamat Domisili');
            $application->rt = $request->input('rt', $profile?->rt ?? '001');
            $application->rw = $request->input('rw', $profile?->rw ?? '001');
            $application->desa = $request->input('desa', $profile?->desa ?? config('village.nama_desa', 'Sukamaju'));
            $application->kecamatan = $request->input('kecamatan', $profile?->kecamatan ?? config('village.nama_kecamatan', 'Ciomas'));
            $application->kabupaten = $request->input('kabupaten', $profile?->kabupaten ?? config('village.nama_kabupaten', 'Kabupaten Bogor'));
            $application->provinsi = $request->input('provinsi', $profile?->provinsi ?? config('village.nama_provinsi', 'Jawa Barat'));

            // Data Ekonomi & Keperluan
            $keperluan = $request->input('keperluan');
            if ($keperluan === 'Lainnya' && $request->filled('keperluan_custom')) {
                $keperluan = $request->input('keperluan_custom');
            }
            $application->keperluan = $keperluan;
            $application->pekerjaan = $request->input('pekerjaan', $profile?->pekerjaan);
            $application->penghasilan_bulanan = $request->input('penghasilan_bulanan');
            $application->jumlah_tanggungan = (int) $request->input('jumlah_tanggungan', 1);
            $application->kondisi_ekonomi = $request->input('kondisi_ekonomi', 'Belum diisi (Draf)');
            $application->catatan = $request->input('catatan');

            // Berkas Dokumen Persyaratan
            if ($ktpData) {
                $application->dokumen_ktp_path = $ktpData['path'];
                $application->dokumen_ktp_original_name = $ktpData['name'];
                $application->dokumen_ktp_size = $ktpData['size'];
            }
            if ($kkData) {
                $application->dokumen_kk_path = $kkData['path'];
                $application->dokumen_kk_original_name = $kkData['name'];
                $application->dokumen_kk_size = $kkData['size'];
            }
            if ($pendukungData) {
                $application->dokumen_pendukung_path = $pendukungData['path'];
                $application->dokumen_pendukung_original_name = $pendukungData['name'];
                $application->dokumen_pendukung_size = $pendukungData['size'];
            }

            // Status Awal Sesuai Alur Pelayanan SIPEDES
            if ($isSubmitting) {
                $application->status = SktmStatus::SUBMITTED;
                $application->tanggal_pengajuan = now();
                $application->save();

                $application->statusHistories()->create([
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'role' => $user->role->value,
                    'from_status' => null,
                    'to_status' => SktmStatus::SUBMITTED->value,
                    'catatan' => 'Permohonan pertama kali diajukan oleh pemohon melalui portal SIPEDES.',
                ]);
            } else {
                $application->status = SktmStatus::DRAFT;
                $application->tanggal_pengajuan = null;
                $application->save();
            }

            return $application;
        });

        // Kirim notifikasi sistem & WhatsApp secara non-blocking setelah database tersimpan
        if ($isSubmitting) {
            try {
                app(NotificationService::class)->notifySubmitted($application);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Gagal notifikasi submitted: ' . $e->getMessage());
            }
        }

        $message = $isSubmitting
            ? "Permohonan SKTM dengan nomor {$application->nomor_pengajuan} berhasil dikirim dan tersimpan di sistem. Permohonan Anda langsung masuk ke antrian pemeriksaan petugas desa."
            : "Draf permohonan SKTM ({$application->nomor_pengajuan}) berhasil disimpan. Anda dapat melengkapinya kapan saja sebelum mengirim.";

        return redirect()->route('warga.sktm.show', $application->id)
            ->with('status', $message);
    }

    /**
     * Tampilkan Rincian Detail Pengajuan SKTM.
     */
    public function show(SktmApplication $sktm): View
    {
        $this->authorizeAccess($sktm);
        $sktm->load(['statusHistories.user', 'user.residentProfile']);

        return view('sktm.show', compact('sktm'));
    }

    /**
     * Tampilkan Halaman Status dan Pelacakan Alur Pengajuan.
     */
    public function status(SktmApplication $sktm): View
    {
        $this->authorizeAccess($sktm);
        $sktm->load(['statusHistories.user', 'user.residentProfile']);

        return view('sktm.status', compact('sktm'));
    }

    /**
     * Tampilkan Formulir Edit Draf SKTM.
     */
    public function edit(SktmApplication $sktm): View|RedirectResponse
    {
        $user = Auth::user();

        if ($sktm->user_id !== $user->id) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki wewenang mengubah permohonan ini.');
        }

        if (! $sktm->canBeEditedBy($user)) {
            return redirect()->route('warga.sktm.show', $sktm->id)
                ->with('error', 'Permohonan ini telah dikirim resmi ke antrian pelayanan dan tidak dapat diedit kembali.');
        }

        $commonPurposes = [
            'Keringanan Biaya Pendidikan / Beasiswa Sekolah & Kuliah',
            'Pendaftaran BPJS PBI (Bantuan Iuran Jaminan Kesehatan)',
            'Keringanan Biaya Pengobatan Rawat Inap RSUD',
            'Bantuan Pasang Baru Listrik Bersubsidi PLN 450VA',
            'Bantuan Permohonan Bantuan Hukum / Advokasi Pro Bono',
            'Bantuan Sosial Pangan & PKH',
            'Lainnya',
        ];

        return view('sktm.edit', compact('sktm', 'commonPurposes'));
    }

    /**
     * Perbarui Draf SKTM atau Kirim Pengajuan.
     */
    public function update(StoreSktmApplicationRequest $request, SktmApplication $sktm): RedirectResponse
    {
        $user = Auth::user();

        if (! $sktm->canBeEditedBy($user)) {
            abort(403, 'Akses Ditolak: Permohonan tidak dapat diubah.');
        }

        $action = $request->input('action', 'draft');
        $isSubmitting = ($action === 'submit');

        // Update berkas jika pemohon mengunggah file baru
        $ktpData = $this->handleSecureUpload($request, 'dokumen_ktp');
        $kkData = $this->handleSecureUpload($request, 'dokumen_kk');
        $pendukungData = $this->handleSecureUpload($request, 'dokumen_pendukung');

        if ($ktpData) {
            $this->deleteSecureFile($sktm->dokumen_ktp_path);
            $sktm->dokumen_ktp_path = $ktpData['path'];
            $sktm->dokumen_ktp_original_name = $ktpData['name'];
            $sktm->dokumen_ktp_size = $ktpData['size'];
        }

        if ($kkData) {
            $this->deleteSecureFile($sktm->dokumen_kk_path);
            $sktm->dokumen_kk_path = $kkData['path'];
            $sktm->dokumen_kk_original_name = $kkData['name'];
            $sktm->dokumen_kk_size = $kkData['size'];
        }

        if ($pendukungData) {
            $this->deleteSecureFile($sktm->dokumen_pendukung_path);
            $sktm->dokumen_pendukung_path = $pendukungData['path'];
            $sktm->dokumen_pendukung_original_name = $pendukungData['name'];
            $sktm->dokumen_pendukung_size = $pendukungData['size'];
        }

        // Update isian
        $keperluan = $request->input('keperluan', $sktm->keperluan);
        if ($keperluan === 'Lainnya' && $request->filled('keperluan_custom')) {
            $keperluan = $request->input('keperluan_custom');
        }
        $sktm->keperluan = $keperluan;
        $sktm->pekerjaan = $request->input('pekerjaan', $sktm->pekerjaan);
        $sktm->penghasilan_bulanan = $request->input('penghasilan_bulanan', $sktm->penghasilan_bulanan);
        $sktm->jumlah_tanggungan = (int) $request->input('jumlah_tanggungan', $sktm->jumlah_tanggungan);
        $sktm->kondisi_ekonomi = $request->input('kondisi_ekonomi', $sktm->kondisi_ekonomi);
        $sktm->alamat = $request->input('alamat', $sktm->alamat);
        $sktm->rt = $request->input('rt', $sktm->rt);
        $sktm->rw = $request->input('rw', $sktm->rw);
        $sktm->catatan = $request->input('catatan', $sktm->catatan);

        $previousStatus = $sktm->status;

        if ($isSubmitting) {
            // Validasi apakah KTP dan KK sudah ada
            if (! $sktm->dokumen_ktp_path || ! $sktm->dokumen_kk_path) {
                return back()->withInput()->withErrors([
                    'dokumen_ktp' => 'Dokumen KTP dan KK wajib diunggah sebelum mengirim pengajuan resmi.',
                ]);
            }

            $sktm->status = SktmStatus::SUBMITTED;
            $sktm->tanggal_pengajuan = now();
            $sktm->save();

            $catatanSubmit = $previousStatus === SktmStatus::NEED_REVISION
                ? ($request->input('catatan') ?: 'Pemohon telah memperbaiki data/dokumen dan mengirim ulang permohonan.')
                : 'Pengajuan draf dikirimkan secara resmi oleh pemohon.';

            $sktm->statusHistories()->create([
                'user_id' => $user->id,
                'user_name' => $user->name,
                'role' => $user->role->value,
                'from_status' => $previousStatus instanceof SktmStatus ? $previousStatus->value : (string) $previousStatus,
                'to_status' => SktmStatus::SUBMITTED->value,
                'catatan' => $catatanSubmit,
            ]);

            app(NotificationService::class)->notifySubmitted($sktm);
        } else {
            $sktm->save();
        }

        $message = $isSubmitting
            ? "Permohonan SKTM ({$sktm->nomor_pengajuan}) berhasil dikirim secara resmi dan sedang menunggu pemeriksaan petugas desa."
            : "Pembaruan draf ({$sktm->nomor_pengajuan}) berhasil disimpan.";

        return redirect()->route('warga.sktm.show', $sktm->id)
            ->with('status', $message);
    }

    /**
     * Aksi Cepat Kirim Draf Langsung dari Halaman Detail.
     */
    public function submitDraft(SktmApplication $sktm): RedirectResponse
    {
        $user = Auth::user();

        if (! $sktm->canBeEditedBy($user)) {
            abort(403, 'Akses Ditolak.');
        }

        if (! $sktm->dokumen_ktp_path || ! $sktm->dokumen_kk_path) {
            return redirect()->route('warga.sktm.edit', $sktm->id)
                ->with('error', 'Dokumen e-KTP dan Kartu Keluarga wajib diunggah sebelum mengirimkan permohonan resmi.');
        }

        $oldStatus = $sktm->status;
        $sktm->status = SktmStatus::SUBMITTED;
        $sktm->tanggal_pengajuan = now();
        $sktm->save();

        $catatanResubmit = $oldStatus === SktmStatus::NEED_REVISION
            ? 'Pemohon mengirim ulang pengajuan setelah melengkapi perbaikan berkas.'
            : 'Pengajuan draf dikirimkan secara resmi oleh pemohon.';

        $sktm->statusHistories()->create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'role' => $user->role->value,
            'from_status' => $oldStatus instanceof SktmStatus ? $oldStatus->value : (string) $oldStatus,
            'to_status' => SktmStatus::SUBMITTED->value,
            'catatan' => $catatanResubmit,
        ]);

        app(NotificationService::class)->notifySubmitted($sktm);

        return redirect()->route('warga.sktm.show', $sktm->id)
            ->with('status', "Permohonan ({$sktm->nomor_pengajuan}) berhasil dikirimkan secara resmi.");
    }

    /**
     * Membatalkan permohonan SKTM oleh pemohon (Warga).
     * Dapat membatalkan draft atau permohonan yang berstatus SUBMITTED (belum diproses).
     */
    public function cancel(Request $request, SktmApplication $sktm): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->id === $sktm->user_id, 403, 'Akses Ditolak: Anda tidak memiliki wewenang membatalkan permohonan ini.');

        if (! in_array($sktm->status, [SktmStatus::DRAFT, SktmStatus::SUBMITTED], true)) {
            return back()->with('error', 'Permohonan tidak dapat dibatalkan karena sudah dalam proses pemeriksaan atau verifikasi.');
        }

        if ($sktm->status === SktmStatus::DRAFT) {
            $nomor = $sktm->nomor_pengajuan;
            $sktm->delete();
            return redirect()->route('warga.sktm.index')->with('status', "Draf permohonan ({$nomor}) berhasil dibatalkan dan dihapus.");
        }

        $sktm->recordStatusTransition(
            user: $user,
            newStatus: SktmStatus::REJECTED,
            notes: 'Permohonan dibatalkan secara mandiri oleh pemohon sebelum diproses petugas.'
        );

        return redirect()->route('warga.sktm.index')
            ->with('status', "Permohonan ({$sktm->nomor_pengajuan}) telah berhasil dibatalkan.");
    }

    /**
     * Unduh / Lihat Dokumen secara Aman dari Private Storage.
     */
    public function downloadDocument(SktmApplication $sktm, string $type): StreamedResponse
    {
        $this->authorizeAccess($sktm);

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
     * Unduh Surat Resmi SKTM yang telah Diterbitkan (PDF).
     */
    public function downloadIssuedPdf(SktmApplication $sktm, SktmDocumentService $documentService): StreamedResponse
    {
        return $documentService->downloadPdf($sktm, Auth::user());
    }

    /**
     * Pratinjau Streaming Surat Resmi SKTM yang telah Diterbitkan (PDF).
     */
    public function previewIssuedPdf(SktmApplication $sktm, SktmDocumentService $documentService): StreamedResponse
    {
        return $documentService->streamPdf($sktm, Auth::user());
    }

    /**
     * Helper upload aman dengan validasi whitelist ekstensi dan UUID di private storage.
     */
    protected function handleSecureUpload(Request $request, string $field): ?array
    {
        if (! $request->hasFile($field)) {
            return null;
        }

        $file = $request->file($field);
        if (! $file->isValid()) {
            return null;
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];

        // Strict extension whitelist defence-in-depth
        if (! in_array($extension, $allowedExtensions, true)) {
            return null;
        }

        // Sanitasi nama asli berkas (mencegah header injection / path traversal)
        $cleanOriginalName = preg_replace('/[^\w\s\.-]/', '_', basename($file->getClientOriginalName()));
        $cleanOriginalName = preg_replace('/\s+/', ' ', trim($cleanOriginalName));
        if ($cleanOriginalName === '' || $cleanOriginalName === '.') {
            $cleanOriginalName = 'dokumen.'.$extension;
        }

        $size = $file->getSize();

        // Nama file acak UUID demi mencegah path traversal & file collision
        $safeFileName = Str::uuid()->toString().'.'.$extension;
        $directory = 'sktm_documents/'.date('Y/m');

        $storedPath = $file->storeAs($directory, $safeFileName, 'local');

        return [
            'path' => $storedPath,
            'name' => $cleanOriginalName,
            'size' => $size,
        ];
    }

    /**
     * Hapus file lama jika ada penggantian berkas.
     */
    protected function deleteSecureFile(?string $path): void
    {
        if ($path && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * Memastikan hanya pemohon pemilik pengajuan atau aparat berwenang yang dapat melihat.
     */
    protected function authorizeAccess(SktmApplication $sktm): void
    {
        $user = Auth::user();

        $isOwner = $sktm->user_id === $user->id;
        $isOfficial = $user->isAdmin() || $user->isStaff() || $user->isSupervisor() || $user->isKepalaDesa();

        if (! $isOwner && ! $isOfficial) {
            abort(403, 'Akses Ditolak (403): Anda tidak berhak melihat data permohonan SKTM warga lain.');
        }
    }
}
