<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\SktmApplication;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use DomainException;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SktmDocumentService
{
    /**
     * Siapkan seluruh data yang dibutuhkan untuk template dokumen SKTM.
     * Mengambil data pemohon dari database dan konfigurasi desa.
     *
     * @return array<string, mixed>
     */
    public function prepareDocumentData(SktmApplication $sktm): array
    {
        $kadesApprovalLog = $sktm->latestKadesApproval();

        $kadesUser = null;
        if ($kadesApprovalLog && $kadesApprovalLog->user) {
            $kadesUser = $kadesApprovalLog->user;
        } else {
            $kadesUser = User::where('role', UserRole::KEPALA_DESA)->first();
        }

        $namaDesa = $sktm->desa ?: config('village.nama_desa', 'Sukamaju');
        $namaKecamatan = $sktm->kecamatan ?: config('village.nama_kecamatan', 'Ciomas');
        $namaKabupaten = $sktm->kabupaten ?: config('village.nama_kabupaten', 'Kabupaten Bogor');
        $namaProvinsi = $sktm->provinsi ?: config('village.nama_provinsi', 'Jawa Barat');

        $kadesNama = $kadesApprovalLog?->user_name
            ?? $kadesUser?->name
            ?? config('village.kepala_desa.nama', 'H. Suryono, S.Sos.');

        $kadesJabatan = 'Kepala Desa '.$namaDesa;
        $kadesNip = config('village.kepala_desa.nip', '19750812 200212 1 003');

        $tanggalSurat = ($sktm->tanggal_disetujui ?? now())->isoFormat('D MMMM Y');

        $village = [
            'nama_desa' => $namaDesa,
            'nama_kecamatan' => $namaKecamatan,
            'nama_kabupaten' => $namaKabupaten,
            'nama_provinsi' => $namaProvinsi,
            'kode_pos' => config('village.kode_pos', '16610'),
            'alamat_kantor' => config('village.alamat_kantor', 'Jl. Raya Sukamaju No. 123'),
            'telepon' => config('village.telepon', '(0251) 863xxxx'),
            'email' => config('village.email', 'desasukamaju@bogorkab.go.id'),
            'website' => config('village.website', 'sukamaju.desa.id'),
            'sktm' => config('village.sktm', [
                'kode_surat' => '470',
                'judul_surat' => 'SURAT KETERANGAN TIDAK MAMPU',
            ]),
        ];

        $kades = [
            'nama' => $kadesNama,
            'jabatan' => $kadesJabatan,
            'nip' => $kadesNip,
        ];

        return [
            'sktm' => $sktm,
            'village' => $village,
            'kades' => $kades,
            'tanggal_surat' => $tanggalSurat,
        ];
    }

    /**
     * Generate berkas fisik PDF SKTM resmi dan simpan metadata ke database.
     * Trigger: Pengajuan berstatus APPROVED.
     */
    public function generatePdf(SktmApplication $sktm, bool $force = false): SktmApplication
    {
        if (! $sktm->isApproved()) {
            throw new DomainException('Dokumen SKTM hanya dapat dibuat untuk pengajuan yang telah disetujui (APPROVED) oleh Kepala Desa.');
        }

        // Jika berkas sudah pernah dibuat dan tidak dipaksa regenerate, kembalikan langsung
        if (! $force && $sktm->file_surat_path && Storage::disk('local')->exists($sktm->file_surat_path)) {
            return $sktm;
        }

        // Pastikan nomor surat unik tersedia
        if (! $sktm->nomor_surat) {
            $sktm->nomor_surat = SktmApplication::generateNomorSurat();
            $sktm->save();
        }

        $data = $this->prepareDocumentData($sktm);

        $html = view('documents.sktm', $data)->render();

        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('a4', 'portrait');
        $output = $pdf->output();

        $year = date('Y');
        $slugNomor = preg_replace('/[^A-Za-z0-9_-]/', '_', $sktm->nomor_surat);
        $fileName = "SKTM_{$slugNomor}.pdf";
        $storagePath = "sktm_documents/generated/{$year}/{$fileName}";

        Storage::disk('local')->put($storagePath, $output);

        $sktm->file_surat_path = $storagePath;
        $sktm->file_surat_name = $fileName;
        $sktm->file_surat_size = strlen($output);
        $sktm->file_surat_generated_at = now();
        $sktm->file_surat_hash = hash('sha256', $output);
        $sktm->is_ready_for_issuance = true;
        $sktm->save();

        return $sktm;
    }

    /**
     * Periksa apakah pengguna memiliki hak akses untuk mengunduh dokumen SKTM.
     */
    public function canUserDownload(SktmApplication $sktm, ?User $user): bool
    {
        if (! $user) {
            return false;
        }

        // Pemohon yang memiliki berkas
        if ($sktm->user_id === $user->id) {
            return true;
        }

        // Pejabat berwenang: Kades, Staff, Supervisor, atau Admin
        if ($user->isKepalaDesa() || $user->isStaff() || $user->isSupervisor() || $user->isAdmin()) {
            return true;
        }

        return false;
    }

    /**
     * Download dokumen fisik PDF SKTM resmi (attachment).
     */
    public function downloadPdf(SktmApplication $sktm, User $user): StreamedResponse
    {
        abort_unless(
            $this->canUserDownload($sktm, $user),
            403,
            'Akses Ditolak: Anda tidak memiliki wewenang untuk mengunduh dokumen SKTM ini.'
        );

        abort_unless(
            $sktm->isApproved(),
            400,
            'Dokumen SKTM belum dapat diunduh karena permohonan belum berstatus disetujui (APPROVED).'
        );

        if (! $sktm->file_surat_path || ! Storage::disk('local')->exists($sktm->file_surat_path)) {
            $this->generatePdf($sktm);
            $sktm->refresh();
        }

        return Storage::disk('local')->download(
            $sktm->file_surat_path,
            $sktm->file_surat_name ?? 'SKTM.pdf',
            [
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-cache, must-revalidate',
            ]
        );
    }

    /**
     * Pratinjau dokumen PDF SKTM resmi di browser (inline).
     */
    public function streamPdf(SktmApplication $sktm, User $user): StreamedResponse
    {
        abort_unless(
            $this->canUserDownload($sktm, $user),
            403,
            'Akses Ditolak: Anda tidak memiliki wewenang untuk melihat dokumen SKTM ini.'
        );

        abort_unless(
            $sktm->isApproved(),
            400,
            'Dokumen SKTM belum dapat dilihat karena permohonan belum berstatus disetujui (APPROVED).'
        );

        if (! $sktm->file_surat_path || ! Storage::disk('local')->exists($sktm->file_surat_path)) {
            $this->generatePdf($sktm);
            $sktm->refresh();
        }

        return Storage::disk('local')->response(
            $sktm->file_surat_path,
            $sktm->file_surat_name ?? 'SKTM.pdf',
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.($sktm->file_surat_name ?? 'SKTM.pdf').'"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-cache, must-revalidate',
            ]
        );
    }
}
