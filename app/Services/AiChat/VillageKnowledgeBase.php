<?php

namespace App\Services\AiChat;

class VillageKnowledgeBase
{
    /**
     * Informasi Resmi Basis Pengetahuan Pelayanan Desa Sukamaju.
     *
     * @return array<string, array{title: string, keywords: list<string>, answer: string}>
     */
    public static function getKnowledgeItems(): array
    {
        return [
            'definisi_sktm' => [
                'title' => 'Pengertian Surat Keterangan Tidak Mampu (SKTM)',
                'keywords' => ['apa itu sktm', 'pengertian sktm', 'definisi sktm', 'arti sktm', 'maksud sktm', 'fungsi sktm', 'kegunaan sktm'],
                'answer' => "Surat Keterangan Tidak Mampu (SKTM) adalah dokumen resmi yang diterbitkan oleh Pemerintah Desa Sukamaju untuk menerangkan bahwa suatu keluarga atau warga benar-benar berada dalam kondisi ekonomi kurang mampu/rentan.\n\nFungsi utama SKTM:\n1. Keringanan atau pembebasan biaya perawatan kesehatan/Rumah Sakit dan pendaftaran BPJS PBI (Penerima Bantuan Iuran).\n2. Pengajuan beasiswa pendidikan, keringanan biaya sekolah, atau Uang Kuliah Tunggal (UKT).\n3. Persyaratan permohonan bantuan sosial (bansos) resmi dari pemerintah.",
            ],

            'persyaratan_sktm' => [
                'title' => 'Persyaratan Permohonan SKTM',
                'keywords' => ['apa saja persyaratan', 'syarat sktm', 'persyaratan sktm', 'syaratnya apa', 'syarat pengajuan', 'apa yang dibutuhkan'],
                'answer' => "Persyaratan resmi permohonan SKTM di Desa Sukamaju:\n\n1. e-KTP Pemohon (Asli atau foto/scan yang jelas dan masih berlaku).\n2. Kartu Keluarga (KK) yang masih berlaku dan memuat nama pemohon.\n3. Formulir pengajuan online yang diisi lengkap (identitas, pekerjaan, penghasilan bulanan, jumlah tanggungan, dan rincian kondisi ekonomi).\n4. Dokumen pendukung seperti Surat Pengantar RT/RW setempat (opsional tetapi sangat disarankan untuk mempercepat verifikasi).",
            ],

            'cara_mengajukan' => [
                'title' => 'Tata Cara Pengajuan SKTM Secara Mandiri',
                'keywords' => ['bagaimana cara mengajukan', 'cara ajukan', 'cara buat sktm', 'alur pengajuan', 'langkah pengajuan', 'tahap pengajuan', 'cara mendaftar sktm'],
                'answer' => "Langkah-langkah mengajukan SKTM secara mandiri di sistem SIPELDES:\n\n1. Masuk (Login) ke akun warga Anda di sistem SIPELDES.\n2. Klik menu \"Ajukan SKTM\" pada bilah menu samping.\n3. Lengkapi formulir pendaftaran: maksud keperluan surat, pekerjaan, estimasi penghasilan, jumlah tanggungan keluarga, dan penjelasan kondisi ekonomi rumah tinggal.\n4. Unggah foto atau scan dokumen e-KTP dan Kartu Keluarga (format JPG, PNG, atau PDF).\n5. Klik tombol \"Kirim Pengajuan Sekarang\".\n6. Pengajuan Anda akan otomatis masuk ke antrian pemeriksaan petugas desa.",
            ],

            'estimasi_waktu' => [
                'title' => 'Waktu Pemrosesan & Jam Pelayanan',
                'keywords' => ['berapa lama prosesnya', 'berapa hari', 'durasi proses', 'lama pembuatan', 'kapan selesai', 'estimasi waktu', 'jam pelayanan', 'jam buka'],
                'answer' => "Estimasi waktu pemrosesan SKTM adalah 1 hingga 3 hari kerja pada hari dan jam operasional kantor desa.\n\nAlur tahapan:\n1. Pemeriksaan berkas oleh Staff Desa.\n2. Verifikasi faktual dan uji kelayakan oleh Supervisor / Kepala Seksi Pelayanan.\n3. Persetujuan akhir dan penomoran surat otomatis oleh Kepala Desa.\n\nJam Pelayanan Kantor Desa:\nSenin – Jumat, pukul 08.00 – 15.00 WIB (Hari Sabtu, Minggu, dan Libur Nasional tutup).",
            ],

            'cara_melihat_status' => [
                'title' => 'Cara Melihat & Melacak Status Pengajuan',
                'keywords' => ['bagaimana cara melihat status', 'cara cek status', 'status pengajuan', 'lacak pengajuan', 'tracking', 'pantau berkas', 'cek berkas'],
                'answer' => "Anda dapat memantau status pengajuan SKTM Anda secara langsung (real-time):\n\n1. Buka menu \"Riwayat Pengajuan\" pada akun warga Anda.\n2. Pilih pengajuan yang ingin dilihat, lalu klik tombol \"Lacak Status\".\n3. Anda akan melihat 6 tahapan timeline transparan:\n   • 1. Pengajuan Dibuat\n   • 2. Diajukan oleh Pemohon\n   • 3. Pemeriksaan Staff\n   • 4. Verifikasi Supervisor\n   • 5. Persetujuan Kepala Desa\n   • 6. SKTM Diterbitkan (siap diunduh)\n4. Anda juga akan menerima notifikasi setiap kali ada perubahan status atau catatan dari petugas.",
            ],

            'mengapa_perlu_perbaikan' => [
                'title' => 'Penyebab Pengajuan Berstatus Perlu Perbaikan (NEED_REVISION)',
                'keywords' => ['mengapa pengajuan saya perlu diperbaiki', 'perlu perbaikan', 'need_revision', 'kenapa revisi', 'alasan perbaikan', 'berkas salah', 'kenapa perlu diperbaiki'],
                'answer' => "Status \"Perlu Perbaikan\" (NEED_REVISION) diberikan oleh petugas apabila berkas atau data permohonan belum memenuhi syarat administrasi, antara lain:\n\n1. Foto dokumen e-KTP atau Kartu Keluarga buram, gelap, miring, terpotong, atau tidak terbaca oleh petugas/sistem OCR.\n2. Terdapat perbedaan data antara yang diketik pada formulir dengan dokumen fisik (misal: NIK atau Nomor KK salah ketik).\n3. Keterangan alasan/keperluan permohonan atau uraian kondisi ekonomi belum diisi dengan jelas.\n\nCara memperbaiki:\nBuka detail pengajuan Anda di menu \"Riwayat Pengajuan\", baca catatan perbaikan dari petugas, klik tombol \"Perbaiki Berkas\", perbarui data atau unggah ulang dokumen yang diminta, lalu kirim kembali.",
            ],

            'dokumen_upload' => [
                'title' => 'Dokumen yang Harus Di-upload',
                'keywords' => ['dokumen apa yang harus di-upload', 'dokumen apa yang harus diupload', 'file apa yang diupload', 'dokumen wajib', 'upload apa saja', 'berkas yang diunggah'],
                'answer' => "Dokumen yang wajib diunggah pada saat mengajukan SKTM:\n\n1. Foto/Scan e-KTP Pemohon:\n   • Format file: JPG, PNG, atau PDF.\n   • Ukuran maksimal: 2 MB.\n   • Pastikan seluruh tulisan, foto, dan 16 digit NIK terbaca dengan sangat jelas.\n\n2. Foto/Scan Kartu Keluarga (KK):\n   • Format file: JPG, PNG, atau PDF.\n   • Ukuran maksimal: 2 MB.\n   • Pastikan seluruh lembar terlihat utuh dan nomor KK terbaca jelas.\n\n3. Dokumen Pendukung (Opsional):\n   • Surat Pengantar RT/RW atau foto kondisi rumah tinggal (maksimal 2 MB).",
            ],

            'biaya_pelayanan' => [
                'title' => 'Biaya Pelayanan SKTM',
                'keywords' => ['biaya', 'apakah bayar', 'berapa biayanya', 'gratis atau bayar', 'tarif sktm'],
                'answer' => 'Pelayanan pembuatan Surat Keterangan Tidak Mampu (SKTM) di Pemerintah Desa Sukamaju adalah 100% GRATIS dan bebas dari segala bentuk pungutan liar (pungli). Jika ada pihak yang memungut biaya, silakan laporkan ke kantor desa.',
            ],
        ];
    }

    /**
     * Pesan default saat pertanyaan masyarakat tidak tercantum di basis data resmi desa.
     */
    public static function getFallbackResponse(): string
    {
        return "Mohon maaf, pertanyaan Anda belum tercantum dalam basis data resmi pelayanan SKTM kami. Untuk memastikan keakuratan informasi dan menghindari aturan yang tidak sesuai, silakan menghubungi petugas pelayanan desa kami secara langsung:\n\n"
            ."🏛️ Kantor Pemerintah Desa Sukamaju\n"
            ."📍 Alamat: Jl. Raya Sukamaju No. 1, Kec. Cibinong, Kab. Bogor, Jawa Barat\n"
            ."🕒 Jam Operasional: Senin – Jumat, pukul 08.00 – 15.00 WIB\n"
            ."📞 WhatsApp Pelayanan: 0812-3456-7890\n"
            .'✉️ Email Resmi: pelayanan@sukamaju.desa.id';
    }

    /**
     * Mencari jawaban dari knowledge base secara semantik / pencocokan kata kunci yang akurat.
     */
    public static function findAnswer(string $userQuery): ?string
    {
        $normalizedQuery = strtolower(trim($userQuery));
        $cleanQuery = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalizedQuery);

        $items = self::getKnowledgeItems();

        // 1. Cek pencocokan frasa kata kunci persis
        foreach ($items as $item) {
            foreach ($item['keywords'] as $keyword) {
                if (str_contains($cleanQuery, strtolower($keyword))) {
                    return $item['answer'];
                }
            }
        }

        // 2. Intent matching berbasis topik spesifik
        // Mencegah generic words seperti "bagaimana cara" salah mencocokkan topik di luar SKTM (misal: visa, paspor, dsb)
        $hasUpload = str_contains($cleanQuery, 'upload') || str_contains($cleanQuery, 'unggah');
        $hasDoc = str_contains($cleanQuery, 'dokumen') || str_contains($cleanQuery, 'berkas') || str_contains($cleanQuery, 'file');
        if ($hasUpload && $hasDoc) {
            return $items['dokumen_upload']['answer'];
        }

        $hasPerbaikan = str_contains($cleanQuery, 'perbaiki') || str_contains($cleanQuery, 'perbaikan') || str_contains($cleanQuery, 'revisi') || str_contains($cleanQuery, 'need revision');
        if ($hasPerbaikan) {
            return $items['mengapa_perlu_perbaikan']['answer'];
        }

        $hasStatus = str_contains($cleanQuery, 'status') || str_contains($cleanQuery, 'lacak') || str_contains($cleanQuery, 'tracking');
        if ($hasStatus) {
            return $items['cara_melihat_status']['answer'];
        }

        $hasWaktu = str_contains($cleanQuery, 'berapa lama') || str_contains($cleanQuery, 'durasi') || str_contains($cleanQuery, 'berapa hari') || str_contains($cleanQuery, 'kapan selesai');
        if ($hasWaktu) {
            return $items['estimasi_waktu']['answer'];
        }

        $hasSyarat = str_contains($cleanQuery, 'syarat') || str_contains($cleanQuery, 'persyaratan');
        if ($hasSyarat) {
            return $items['persyaratan_sktm']['answer'];
        }

        $hasDefinisi = (str_contains($cleanQuery, 'apa itu') || str_contains($cleanQuery, 'definisi') || str_contains($cleanQuery, 'pengertian'))
            && (str_contains($cleanQuery, 'sktm') || str_contains($cleanQuery, 'surat tidak mampu'));
        if ($hasDefinisi) {
            return $items['definisi_sktm']['answer'];
        }

        $hasCara = str_contains($cleanQuery, 'cara') || str_contains($cleanQuery, 'langkah') || str_contains($cleanQuery, 'alur') || str_contains($cleanQuery, 'tahap');
        $hasAjukan = str_contains($cleanQuery, 'ajukan') || str_contains($cleanQuery, 'mengajukan') || str_contains($cleanQuery, 'buat sktm') || str_contains($cleanQuery, 'bikin sktm');
        if ($hasCara && $hasAjukan) {
            return $items['cara_mengajukan']['answer'];
        }

        $hasBiaya = str_contains($cleanQuery, 'biaya') || str_contains($cleanQuery, 'bayar') || str_contains($cleanQuery, 'tarif') || str_contains($cleanQuery, 'gratis');
        if ($hasBiaya && (str_contains($cleanQuery, 'sktm') || str_contains($cleanQuery, 'surat'))) {
            return $items['biaya_pelayanan']['answer'];
        }

        return null;
    }

    /**
     * Membentuk System Prompt lengkap untuk Model LLM (seperti Gemini).
     */
    public static function getSystemPrompt(): string
    {
        $knowledgeText = '';
        foreach (self::getKnowledgeItems() as $item) {
            $knowledgeText .= "### {$item['title']}\n{$item['answer']}\n\n";
        }

        $fallback = self::getFallbackResponse();

        return <<<PROMPT
Anda adalah "Asisten AI Pelayanan Desa Sukamaju", bertugas membantu masyarakat memahami prosedur, persyaratan, dan alur pelayanan Surat Keterangan Tidak Mampu (SKTM).

ATURAN SANGAT KETAT:
1. Anda HANYA boleh menjawab pertanyaan berdasarkan INFORMASI RESMI DESA di bawah ini.
2. JANGAN PERNAH membuat aturan, dokumen baru, biaya, tarif, atau ketentuan yang tidak ada di dalam informasi resmi.
3. Seluruh pelayanan SKTM adalah 100% GRATIS.
4. JIKA PERTANYAAN PENGGUNA TIDAK DIKETAHUI atau di luar informasi resmi di bawah, JANGAN mengarang atau berspekulasi. Anda WAJIB mengarahkan pengguna menghubungi petugas/kantor desa dengan teks berikut:
"{$fallback}"
5. Gunakan bahasa Indonesia yang santun, ramah, jelas, dan mudah dipahami oleh seluruh lapisan masyarakat desa.

INFORMASI RESMI DESA SUKAMAJU:
{$knowledgeText}
PROMPT;
    }
}
