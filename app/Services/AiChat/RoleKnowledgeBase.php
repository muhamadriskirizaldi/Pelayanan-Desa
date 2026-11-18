<?php

namespace App\Services\AiChat;

use App\Enums\UserRole;
use App\Models\User;

class RoleKnowledgeBase
{
    /**
     * Mengambil metadata antarmuka dan konteks untuk role tertentu.
     *
     * @return array{
     *     title: string,
     *     subtitle: string,
     *     welcome: string,
     *     placeholder: string,
     *     badge_label: string,
     *     badge_class: string,
     *     prompts: list<string>
     * }
     */
    public static function getRoleUiContext(UserRole|string $role, ?string $userName = null): array
    {
        $roleValue = $role instanceof UserRole ? $role->value : (string) $role;
        $name = $userName ? " {$userName}" : '';

        return match ($roleValue) {
            'masyarakat' => [
                'title' => 'SIPEDES INTELLIGENCE',
                'subtitle' => 'Smart Village AI Copilot &bull; Layanan Warga',
                'welcome' => "Halo Bapak/Ibu{$name}! 👋 Saya **SIPEDES AI Copilot**.\n\nAsisten cerdas resmi Desa Sukamaju yang siap mendampingi Anda memahami persyaratan surat, alur pengajuan SKTM, pelacakan berkas, hingga panduan perbaikan dokumen.\n\nSilakan pilih topik cepat di bawah atau ketik pertanyaan Anda.",
                'placeholder' => 'Ask SIPEDES AI anything...',
                'badge_label' => 'Masyarakat',
                'badge_class' => 'badge-masyarakat',
                'prompts' => [
                    'Cara membuat SKTM',
                    'Persyaratan surat',
                    'Cek status pengajuan',
                    'Apa itu SKTM?',
                    'Persyaratan SKTM',
                    'Cara Mengajukan',
                    'Dokumen yang Di-upload',
                    'Berapa lama proses pembuatan SKTM?',
                    'Apakah ada biaya untuk pembuatan SKTM?',
                    'Mengapa pengajuan saya berstatus perlu perbaikan?',
                ],
            ],

            'staff' => [
                'title' => 'SIPEDES INTELLIGENCE',
                'subtitle' => 'Smart Village AI Copilot &bull; Staff Administrasi',
                'welcome' => "Selamat bertugas, Rekan Staff{$name}! 📋 Saya **SIPEDES AI Copilot**.\n\nSistem pendamping cerdas untuk memandu pemeriksaan dokumen fisik, pembacaan rekomendasi AI Document Check, pengelolaan status permohonan warga, dan kriteria kelengkapan administrasi sebelum diajukan ke Supervisor.",
                'placeholder' => 'Ask SIPEDES AI anything...',
                'badge_label' => 'Staff Pelayanan',
                'badge_class' => 'badge-staff',
                'prompts' => [
                    'Pemeriksaan dokumen',
                    'Validasi data warga',
                    'Daftar pengajuan baru',
                    'Bagaimana cara membaca hasil AI Document Check?',
                    'Kapan harus meminta perbaikan (NEED_REVISION)?',
                    'Kapan berkas dapat diteruskan ke Supervisor?',
                ],
            ],

            'supervisor' => [
                'title' => 'SIPEDES INTELLIGENCE',
                'subtitle' => 'Smart Village AI Copilot &bull; Supervisor Verifikasi',
                'welcome' => "Salam hormat, Bapak/Ibu Supervisor{$name}! ⚖️ Saya **SIPEDES AI Copilot**.\n\nSistem analitik cerdas untuk memandu verifikasi faktual kelayakan ekonomi pemohon, telaah hasil pemeriksaan staff, evaluasi beban tanggungan keluarga, dan rekomendasi keputusan sebelum diajukan ke Kepala Desa.",
                'placeholder' => 'Ask SIPEDES AI anything...',
                'badge_label' => 'Supervisor',
                'badge_class' => 'badge-supervisor',
                'prompts' => [
                    'Analisis kelayakan pengajuan',
                    'Pemeriksaan hasil verifikasi',
                    'Pengajuan menunggu persetujuan',
                    'Apa saja indikator kelayakan penerima SKTM?',
                    'Kapan Supervisor harus menolak permohonan (REJECTED)?',
                    'Bagaimana SOP pengembalian berkas untuk perbaikan?',
                ],
            ],

            'kepala_desa' => [
                'title' => 'SIPEDES INTELLIGENCE',
                'subtitle' => 'Smart Village AI Copilot &bull; Executive Decision',
                'welcome' => "Salam hormat, Bapak/Ibu Kepala Desa{$name}! 🏛️ Saya **SIPEDES AI Copilot**.\n\nAsisten eksekutif cerdas untuk meninjau ringkasan pelayanan desa, rekam jejak verifikasi berjenjang, permohonan yang menunggu persetujuan akhir (approval), statistik surat, dan penomoran resmi otomatis.",
                'placeholder' => 'Ask SIPEDES AI anything...',
                'badge_label' => 'Kepala Desa',
                'badge_class' => 'badge-kades',
                'prompts' => [
                    'Ringkasan pelayanan',
                    'Pengajuan menunggu approval',
                    'Statistik surat desa',
                    'Bagaimana alur persetujuan akhir Kepala Desa?',
                    'Bagaimana format penomoran surat otomatis desa?',
                    'Apa yang terjadi setelah Kepala Desa menyetujui permohonan?',
                ],
            ],

            'admin' => [
                'title' => 'SIPEDES INTELLIGENCE',
                'subtitle' => 'Smart Village AI Copilot &bull; System Governance',
                'welcome' => "Halo Administrator{$name}! 🛠️ Saya **SIPEDES AI Copilot**.\n\nSistem tata kelola cerdas untuk mendampingi Anda dalam manajemen akun pengguna, pengaturan role & permission (RBAC), audit trail keamanan, konfigurasi master data desa, dan monitoring aktivitas sistem.",
                'placeholder' => 'Ask SIPEDES AI anything...',
                'badge_label' => 'Administrator',
                'badge_class' => 'badge-admin',
                'prompts' => [
                    'Manajemen pengguna',
                    'Pengaturan sistem',
                    'Monitoring aktivitas',
                    'Bagaimana cara mengaktifkan atau menonaktifkan pengguna?',
                    'Bagaimana pembagian hak akses (RBAC) 5 role di SIPELDES?',
                    'Apa saja variabel environment (.env) penting?',
                ],
            ],

            default => [
                'title' => 'Asisten AI Pelayanan Desa',
                'subtitle' => 'Sistem Informasi Pelayanan Desa Terpadu (SIPELDES)',
                'welcome' => "Halo{$name}! Selamat datang di **Asisten AI Pelayanan Desa Sukamaju**.\n\nAda yang dapat saya bantu terkait sistem informasi pelayanan desa?",
                'placeholder' => 'Ketik pertanyaan Anda di sini...',
                'badge_label' => 'Pengguna',
                'badge_class' => 'badge-staff',
                'prompts' => [
                    'Apa itu SIPELDES?',
                    'Bagaimana alur pelayanan 5 tahapan?',
                    'Jam operasional kantor desa?',
                ],
            ],
        };
    }

    /**
     * Tiga Kartu Quick Action Premium untuk Welcome Screen AI Copilot.
     *
     * @return list<array{title: string, desc: string, prompt: string, icon: string}>
     */
    public static function getQuickActionCards(UserRole|string $role): array
    {
        $roleValue = $role instanceof UserRole ? $role->value : (string) $role;

        return match ($roleValue) {
            'masyarakat' => [
                [
                    'title' => 'Cara Membuat SKTM',
                    'desc' => 'Panduan alur 5 tahapan pengajuan daring mandiri',
                    'prompt' => 'Cara membuat SKTM',
                    'icon' => 'document',
                ],
                [
                    'title' => 'Persyaratan Surat',
                    'desc' => 'Ketentuan berkas e-KTP, KK & dokumen pendukung',
                    'prompt' => 'Persyaratan surat',
                    'icon' => 'clipboard',
                ],
                [
                    'title' => 'Cek Status Pengajuan',
                    'desc' => 'Lacak tahapan berkas dari staff hingga kades',
                    'prompt' => 'Cek status pengajuan',
                    'icon' => 'track',
                ],
            ],

            'staff' => [
                [
                    'title' => 'Pemeriksaan Dokumen',
                    'desc' => 'SOP validasi berkas fisik & telaah AI Check',
                    'prompt' => 'Pemeriksaan dokumen',
                    'icon' => 'document',
                ],
                [
                    'title' => 'Validasi Data Warga',
                    'desc' => 'Pencocokan NIK, KK dan catatan perbaikan',
                    'prompt' => 'Validasi data warga',
                    'icon' => 'shield',
                ],
                [
                    'title' => 'Daftar Pengajuan Baru',
                    'desc' => 'Panduan penanganan antrian berkas masuk',
                    'prompt' => 'Daftar pengajuan baru',
                    'icon' => 'inbox',
                ],
            ],

            'supervisor' => [
                [
                    'title' => 'Analisis Kelayakan Pengajuan',
                    'desc' => 'Indikator ekonomi & evaluasi beban tanggungan',
                    'prompt' => 'Analisis kelayakan pengajuan',
                    'icon' => 'scale',
                ],
                [
                    'title' => 'Pemeriksaan Hasil Verifikasi',
                    'desc' => 'Telaah berkas lolos staff & catatan lapangan',
                    'prompt' => 'Pemeriksaan hasil verifikasi',
                    'icon' => 'clipboard',
                ],
                [
                    'title' => 'Pengajuan Menunggu Persetujuan',
                    'desc' => 'SOP rekomendasi ke Kades vs tolak (REJECTED)',
                    'prompt' => 'Pengajuan menunggu persetujuan',
                    'icon' => 'clock',
                ],
            ],

            'kepala_desa' => [
                [
                    'title' => 'Ringkasan Pelayanan',
                    'desc' => 'Ikhtisar permohonan SKTM terverifikasi',
                    'prompt' => 'Ringkasan pelayanan',
                    'icon' => 'chart',
                ],
                [
                    'title' => 'Pengajuan Menunggu Approval',
                    'desc' => 'Legalisasi tanda tangan & nomor surat otomatis',
                    'prompt' => 'Pengajuan menunggu approval',
                    'icon' => 'stamp',
                ],
                [
                    'title' => 'Statistik Surat Desa',
                    'desc' => 'Rasio persetujuan, rata-rata waktu & total terbit',
                    'prompt' => 'Statistik surat desa',
                    'icon' => 'presentation',
                ],
            ],

            'admin' => [
                [
                    'title' => 'Manajemen Pengguna',
                    'desc' => 'Aktivasi, nonaktifkan akun & struktur RBAC',
                    'prompt' => 'Manajemen pengguna',
                    'icon' => 'users',
                ],
                [
                    'title' => 'Pengaturan Sistem',
                    'desc' => 'Konfigurasi environment, master data & kop surat',
                    'prompt' => 'Pengaturan sistem',
                    'icon' => 'cog',
                ],
                [
                    'title' => 'Monitoring Aktivitas',
                    'desc' => 'Audit trail log, notifikasi & integritas sesi',
                    'prompt' => 'Monitoring aktivitas',
                    'icon' => 'shield',
                ],
            ],

            default => [
                [
                    'title' => 'Informasi Pelayanan',
                    'desc' => 'Panduan umum sistem informasi desa',
                    'prompt' => 'Informasi pelayanan',
                    'icon' => 'info',
                ],
                [
                    'title' => 'Persyaratan Surat',
                    'desc' => 'Ketentuan berkas e-KTP dan KK pemohon',
                    'prompt' => 'Persyaratan surat',
                    'icon' => 'clipboard',
                ],
                [
                    'title' => 'Jam Pelayanan',
                    'desc' => 'Waktu operasional kantor desa',
                    'prompt' => 'Jam operasional kantor desa',
                    'icon' => 'clock',
                ],
            ],
        };
    }

    /**
     * =========================================================================
     * SIPEDES AI IDENTITY RESPONSE
     * Respons resmi identitas AI Assistant SIPEDES.
     * =========================================================================
     */
    public static function getIdentityResponse(string $role, string $userName): string
    {
        return "Saya adalah AI Assistant SIPEDES, asisten kecerdasan buatan yang membantu pengguna dalam sistem pelayanan desa.";
    }

    /**
     * =========================================================================
     * SIPEDES AI TASK RESPONSE
     * Respons tugas/fungsi AI berdasarkan role aktif.
     * =========================================================================
     */
    public static function getTaskResponse(string $role): string
    {
        return match ($role) {
            'masyarakat' => "Sebagai **AI Assistant SIPEDES – Asisten Pelayanan Masyarakat**, saya dapat membantu Anda dengan:\n\n"
                ."📋 **Informasi & Prosedur SKTM**\n"
                ."• Panduan lengkap pembuatan Surat Keterangan Tidak Mampu (SKTM).\n"
                ."• Persyaratan dokumen yang harus disiapkan (e-KTP, KK, dll).\n"
                ."• Cara mengisi formulir pengajuan dengan benar.\n\n"
                ."📊 **Status Pengajuan**\n"
                ."• Panduan cara melihat dan melacak status pengajuan Anda.\n"
                ."• Penjelasan setiap status tahapan proses.\n\n"
                ."🖥️ **Panduan SIPEDES**\n"
                ."• Cara menggunakan fitur-fitur sistem SIPEDES.\n"
                ."• Bantuan saat mengalami kendala dalam sistem.",

            'staff' => "Sebagai **AI Assistant SIPEDES – Asisten Administrasi Pelayanan**, saya dapat membantu Anda dengan:\n\n"
                ."📂 **Pemeriksaan Berkas**\n"
                ."• Panduan memeriksa kelengkapan dokumen permohonan.\n"
                ."• SOP validasi e-KTP dan Kartu Keluarga.\n"
                ."• Cara membaca hasil AI Document Check.\n\n"
                ."✅ **Pengelolaan Pengajuan**\n"
                ."• Alur penanganan permohonan baru (SUBMITTED → UNDER_REVIEW).\n"
                ."• Kapan harus meminta perbaikan (NEED_REVISION).\n"
                ."• Kriteria meneruskan berkas ke Supervisor.\n\n"
                ."📋 **Panduan Tugas Staff**\n"
                ."• SOP pemeriksaan administrasi pelayanan desa.",

            'supervisor' => "Sebagai **AI Assistant SIPEDES – Asisten Verifikasi Pelayanan**, saya dapat membantu Anda dengan:\n\n"
                ."⚖️ **Verifikasi & Analisis Kelayakan**\n"
                ."• Indikator penilaian kelayakan ekonomi pemohon SKTM.\n"
                ."• Cara mengevaluasi kondisi finansial dan beban tanggungan keluarga.\n\n"
                ."🔍 **Pemeriksaan Hasil Staff**\n"
                ."• Cara memeriksa kualitas hasil verifikasi staff.\n"
                ."• Panduan pengembalian berkas untuk perbaikan.\n\n"
                ."📤 **Rekomendasi & Persetujuan**\n"
                ."• Panduan meneruskan berkas layak ke Kepala Desa.\n"
                ."• SOP penolakan permohonan tidak layak (REJECTED).",

            'kepala_desa' => "Sebagai **AI Assistant SIPEDES – Asisten Kepala Desa**, saya dapat membantu Anda dengan:\n\n"
                ."📊 **Ringkasan Pelayanan**\n"
                ."• Ikhtisar alur pelayanan SKTM 5 tahapan.\n"
                ."• Statistik permohonan yang disetujui, ditolak, dan sedang diproses.\n\n"
                ."🏛️ **Approval Surat**\n"
                ."• Panduan proses persetujuan akhir (APPROVE) permohonan SKTM.\n"
                ."• Informasi penomoran surat otomatis dan penerbitan PDF resmi.\n\n"
                ."📈 **Statistik & Pemantauan**\n"
                ."• Performa pelayanan desa dan rasio persetujuan.\n"
                ."• Pantauan permohonan yang menunggu tindakan Kepala Desa.",

            'admin' => "Saya adalah **AI Assistant SIPEDES** (Sistem Pelayanan Desa Digital), asisten kecerdasan buatan yang dirancang untuk membantu Anda dalam menggunakan dan mengelola sistem pelayanan desa. Saya siap memberikan informasi, panduan, serta bantuan sesuai dengan hak akses dan peran Anda sebagai Administrator.\n\n"
                ."👥 **Manajemen Pengguna & Role**\n"
                ."• Panduan mengaktifkan / menonaktifkan akun pengguna.\n"
                ."• Penjelasan struktur RBAC 5 role di SIPEDES.\n\n"
                ."⚙️ **Konfigurasi Sistem**\n"
                ."• Pengaturan master data desa (nama desa, kecamatan, kabupaten).\n"
                ."• Konfigurasi environment variable penting (.env).\n"
                ."• Pengaturan driver AI & Document Checker.\n\n"
                ."🔍 **Monitoring & Audit**\n"
                ."• Panduan membaca audit trail dan log aktivitas sistem.\n"
                ."• Informasi keamanan sistem (CSRF, rate limiting, session).",

            default => "Sebagai **AI Assistant SIPEDES**, saya siap membantu Anda dengan informasi dan panduan terkait sistem pelayanan administrasi Desa Sukamaju.",
        };
    }

    /**
     * =========================================================================
     * SIPEDES SCOPE REJECTION
     * Menolak permintaan di luar ruang lingkup SIPEDES.
     * =========================================================================
     */
    public static function getOutOfScopeRejection(string $userQuery): ?string
    {
        $normalized = strtolower(trim($userQuery));

        // Deteksi permintaan coding / pemrograman di luar SIPEDES
        $codingPatterns = [
            'coding python', 'kode python', 'script python', 'program python',
            'coding java', 'kode java', 'program java',
            'coding c++', 'program c++', 'kode c++', 'coding c plus',
            'coding php', 'script php',
            'coding javascript', 'script javascript', 'kode javascript',
            'buat website', 'buat aplikasi android', 'buat app android',
            'buat game', 'coding game',
            'script hacking', 'script hack', 'cara hacking', 'cara hack',
            'sql injection', 'exploit', 'bypass login',
            'membuat program komputer', 'koding untuk', 'algoritma sorting',
            'bubble sort', 'merge sort', 'binary search',
            'tugas pemrograman', 'pr coding', 'homework coding',
            'luas segitiga python', 'luas segitiga java', 'luas lingkaran program',
            'buat fungsi', 'buat method', 'buat class',
            'tutorial python', 'belajar java', 'belajar c++',
        ];

        foreach ($codingPatterns as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return "Maaf, saya tidak menyediakan pembuatan kode Python. Saya dapat membantu menjelaskan fitur dan penggunaan sistem SIPEDES.";
            }
        }

        return null;
    }

    /**
     * =========================================================================
     * PERTANYAAN IDENTITAS AI
     * Deteksi pertanyaan tentang identitas atau fungsi AI ("Siapa kamu?").
     * =========================================================================
     */
    public static function isIdentityQuestion(string $query): bool
    {
        $normalized = strtolower(trim($query));

        // Jangan tangkap jika pertanyaan tentang identitas PENGGUNA
        if (self::isUserIdentityQuestion($query)) {
            return false;
        }

        $identityPhrases = [
            'siapa kamu', 'kamu siapa', 'apa nama kamu', 'namamu apa', 'nama kamu apa',
            'kamu adalah siapa', 'kamu itu siapa', 'apa fungsi kamu', 'fungsi kamu apa',
            'kamu apa', 'kamu ini apa', 'apa itu kamu', 'kamu dibuat oleh siapa',
            'siapa yang membuat kamu', 'kamu chatgpt', 'kamu gemini', 'kamu claude',
            'kamu gpt', 'kamu ai apa', 'ai apa kamu', 'kamu pakai model apa',
            'kamu pakai ai apa', 'kamu dari mana', 'kamu bisa apa', 'kemampuan kamu',
            'apa kemampuan kamu', 'apa yang bisa kamu lakukan',
            'perkenalkan diri', 'perkenalan', 'introduce yourself',
        ];

        foreach ($identityPhrases as $phrase) {
            if (str_contains($normalized, $phrase)) {
                return true;
            }
        }

        return false;
    }

    /**
     * =========================================================================
     * PERTANYAAN IDENTITAS PENGGUNA
     * Deteksi pertanyaan tentang identitas pengguna sendiri ("Siapa saya?").
     * =========================================================================
     */
    public static function isUserIdentityQuestion(string $query): bool
    {
        $normalized = strtolower(trim($query));

        // Jangan tangkap jika mengandung kata kunci privasi / data orang lain
        $privacyKeywords = ['nik', 'tetangga', 'orang lain', 'warga lain', 'staf desa', 'bocorkan', 'rahasia'];
        foreach ($privacyKeywords as $kw) {
            if (str_contains($normalized, $kw)) {
                return false;
            }
        }

        $userIdentityPhrases = [
            'siapa saya', 'saya siapa', 'saya ini siapa', 'saya itu siapa',
            'apa jabatan saya', 'jabatan saya', 'apa role saya', 'role saya apa',
            'saya login sebagai apa', 'saya masuk sebagai apa',
            'saya ini apa', 'aku siapa', 'aku ini siapa',
            'identitas saya', 'profil saya', 'akun saya ini apa',
            'saya sebagai apa', 'apa posisi saya', 'posisi saya',
            'apa tugas saya', 'tugas saya', 'wewenang saya', 'apa wewenang saya',
            'hak akses saya', 'apa hak akses saya', 'saya punya akses apa',
            'apa yang bisa saya lakukan', 'saya bisa apa', 'saya bisa apa saja',
        ];

        foreach ($userIdentityPhrases as $phrase) {
            if (str_contains($normalized, $phrase)) {
                return true;
            }
        }

        return false;
    }

    /**
     * =========================================================================
     * RESPONS IDENTITAS PENGGUNA
     * Jawaban berdasarkan role pengguna yang sedang login.
     * =========================================================================
     */
    public static function getUserIdentityResponse(string $role, ?string $userName = null): string
    {
        $roleEnum = UserRole::tryFrom($role);
        $roleLabel = $roleEnum ? $roleEnum->label() : ucfirst($role);
        $nameStr = !empty($userName) ? trim($userName) : 'belum tersedia di sistem';

        return "Nama Anda {$nameStr}. Anda login sebagai {$roleLabel} SIPEDES.";
    }

    /**
     * =========================================================================
     * PERTANYAAN TUGAS AI
     * Deteksi pertanyaan tentang tugas/fungsi AI berdasarkan role.
     * =========================================================================
     */
    public static function isTaskQuestion(string $query): bool
    {
        $normalized = strtolower(trim($query));
        $taskPhrases = [
            'apa tugas kamu', 'tugas kamu', 'apa tugasmu', 'tugasmu apa',
            'kamu bisa bantu apa', 'bantu apa saja', 'bisa bantu apa',
            'apa yang bisa kamu bantu', 'kamu bisa apa saja', 'menu bantuan',
            'fitur kamu', 'layanan kamu', 'apa saja yang kamu bisa',
            'kamu bisa membantu apa', 'kamu membantu apa', 'bantuanmu apa',
        ];

        foreach ($taskPhrases as $phrase) {
            if (str_contains($normalized, $phrase)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mencari jawaban cerdas berbasis role pengguna yang sedang login.
     * ROLE SELALU diambil dari objek User (backend Auth) – tidak pernah dari input frontend.
     */
    public static function findAnswerForUser(string $userQuery, User $user): ?string
    {
        // =====================================================================
        // KEAMANAN: Role WAJIB diambil dari objek $user (Auth::user()) di backend.
        // Tidak ada parameter role dari frontend yang dipercaya di sini.
        // =====================================================================
        $role = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
        $normalized = strtolower(trim($userQuery));
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalized);

        // -----------------------------------------------------------------
        // PRIORITAS 1A: Pertanyaan identitas PENGGUNA (siapa saya? apa role saya?)
        // Harus dicek SEBELUM identitas AI agar tidak salah tangkap.
        // -----------------------------------------------------------------
        if (self::isUserIdentityQuestion($userQuery)) {
            return self::getUserIdentityResponse($role, $user->name);
        }

        // -----------------------------------------------------------------
        // PRIORITAS 1B: Pertanyaan identitas AI (siapa kamu? apa nama kamu?)
        // -----------------------------------------------------------------
        if (self::isIdentityQuestion($userQuery)) {
            return self::getIdentityResponse($role, $user->name);
        }

        // -----------------------------------------------------------------
        // PRIORITAS 2: Pertanyaan tugas/fungsi AI
        // -----------------------------------------------------------------
        if (self::isTaskQuestion($userQuery)) {
            return self::getTaskResponse($role);
        }

        // -----------------------------------------------------------------
        // PRIORITAS 3: Penolakan permintaan coding di luar SIPEDES
        // -----------------------------------------------------------------
        $rejection = self::getOutOfScopeRejection($userQuery);
        if ($rejection !== null) {
            return $rejection;
        }

        // -----------------------------------------------------------------
        // PRIORITAS 4: Salam ramah – balas dengan pesan selamat datang role
        // -----------------------------------------------------------------
        if (preg_match('/^(halo|hai|pagi|siang|sore|malam|assalamu[\w\s]+|permisi|tes|hei|hey)/i', $normalized)) {
            $context = self::getRoleUiContext($role, $user->name);
            return $context['welcome'];
        }

        // -----------------------------------------------------------------
        // PRIORITAS 5: Ucapan terima kasih
        // -----------------------------------------------------------------
        if (preg_match('/^(terima kasih|makasih|thanks|matur nuwun|nuhun)/i', $normalized)) {
            return "Sama-sama Bapak/Ibu **{$user->name}**! Senang dapat mendampingi tugas dan pelayanan Anda di Desa Sukamaju. "
                ."Jika ada hal lain yang perlu ditanyakan, saya selalu siap membantu.";
        }

        // -----------------------------------------------------------------
        // PRIORITAS 6: Keamanan & Privasi (UU PDP No. 27/2022)
        // -----------------------------------------------------------------
        if (
            str_contains($clean, 'nik orang lain') ||
            str_contains($clean, 'data warga lain') ||
            str_contains($clean, 'bocorkan data') ||
            str_contains($clean, 'lihat semua nik') ||
            str_contains($clean, 'nik tetangga') ||
            str_contains($clean, 'nik lengkap orang') ||
            str_contains($clean, 'bocorkan') ||
            str_contains($clean, 'data rahasia') ||
            str_contains($clean, 'rahasia staf') ||
            str_contains($clean, 'nik lengkap tetangga') ||
            (str_contains($clean, 'nik') && str_contains($clean, 'tetangga')) ||
            (str_contains($clean, 'nik') && str_contains($clean, 'rahasia'))
        ) {
            return "⚠️ **Peringatan Privasi & Keamanan Data (UU PDP):**\n"
                ."Sistem AI SIPEDES menjunjung tinggi perlindungan data pribadi kependudukan sesuai UU No. 27 Tahun 2022. "
                ."Data identitas NIK dan Kartu Keluarga hanya dapat diakses melalui antrian berkas resmi oleh petugas berwenang "
                ."dan tidak dapat dibagikan melalui percakapan asisten.";
        }

        // -----------------------------------------------------------------
        // PRIORITAS 7: Non-Otorisasi Otomatis
        // -----------------------------------------------------------------
        if (
            str_contains($clean, 'setujui otomatis') ||
            str_contains($clean, 'tolak otomatis') ||
            str_contains($clean, 'rubah data database') ||
            str_contains($clean, 'hapus database')
        ) {
            return "ℹ️ **Pemberitahuan Kewenangan AI:**\n"
                ."Saya adalah **AI Assistant SIPEDES** yang hanya memberikan rekomendasi, panduan, dan informasi. "
                ."Saya **tidak memiliki wewenang** untuk menyetujui, menolak, atau mengubah data permohonan secara otomatis. "
                ."Seluruh keputusan legalitas mutlak dilakukan secara manual oleh petugas/Kepala Desa yang berwenang.";
        }

        // -----------------------------------------------------------------
        // PRIORITAS 8: Pencarian jawaban berbasis Knowledge Base sesuai Role
        // -----------------------------------------------------------------
        return match ($role) {
            'masyarakat'  => self::findMasyarakatAnswer($clean),
            'staff'       => self::findStaffAnswer($clean),
            'supervisor'  => self::findSupervisorAnswer($clean),
            'kepala_desa' => self::findKadesAnswer($clean),
            'admin'       => self::findAdminAnswer($clean),
            default       => self::findMasyarakatAnswer($clean),
        };
    }

    /**
     * Basis Pengetahuan Khusus Role MASYARAKAT.
     */
    protected static function findMasyarakatAnswer(string $clean): ?string
    {
        // 1. Delegasikan ke VillageKnowledgeBase (Official Knowledge Base Pelayanan SKTM)
        $officialAnswer = VillageKnowledgeBase::findAnswer($clean);
        if ($officialAnswer !== null) {
            return $officialAnswer;
        }

        // 2. Dukung pencocokan quick prompts spesifik masyarakat
        if (str_contains($clean, 'cara membuat sktm') || str_contains($clean, 'prosedur sktm')) {
            return VillageKnowledgeBase::getKnowledgeItems()['cara_mengajukan']['answer'];
        }

        if (str_contains($clean, 'persyaratan surat') || str_contains($clean, 'syarat surat')) {
            return VillageKnowledgeBase::getKnowledgeItems()['persyaratan_sktm']['answer'];
        }

        if (str_contains($clean, 'cek status pengajuan') || str_contains($clean, 'status pengajuan')) {
            return VillageKnowledgeBase::getKnowledgeItems()['cara_melihat_status']['answer'];
        }

        if (str_contains($clean, 'download') || str_contains($clean, 'unduh') || str_contains($clean, 'cetak')) {
            return "📥 **Cara Mengunduh Dokumen SKTM:**\n\n"
                ."Setelah status pengajuan Anda berubah menjadi **Disetujui Kepala Desa (APPROVED)**:\n"
                ."1. Buka menu *Riwayat Pengajuan* pada akun warga Anda.\n"
                ."2. Klik tombol **Unduh Dokumen Resmi (PDF)**.\n"
                ."3. Surat PDF resmi berkop desa lengkap dengan nomor registrasi unik dan QR-Code legalitas siap disimpan atau dicetak.";
        }

        return null;
    }

    /**
     * Basis Pengetahuan Khusus Role STAFF.
     */
    protected static function findStaffAnswer(string $clean): ?string
    {
        if (str_contains($clean, 'pemeriksaan dokumen') || str_contains($clean, 'sop') || str_contains($clean, 'pemeriksaan')) {
            return "📋 **SOP Pemeriksaan Dokumen Administrasi oleh Staff:**\n\n"
                ."1. Buka berkas berstatus **Pengajuan Baru (SUBMITTED)** di menu *Pemeriksaan Berkas*.\n"
                ."2. Sistem akan otomatis mengubah status menjadi **Sedang Diperiksa (UNDER_REVIEW)**.\n"
                ."3. Periksa keaslian dan keterbacaan scan e-KTP dan Kartu Keluarga (KK) pemohon.\n"
                ."4. Gunakan fitur *AI Document Check* untuk memeriksa konsistensi Nama, NIK, No KK, dan Alamat.\n"
                ."5. Jika berkas valid, klik **Verifikasi & Teruskan ke Supervisor** (`VERIFIED_BY_STAFF`).\n"
                ."6. Jika berkas buram/salah, klik **Minta Perbaikan (NEED_REVISION)** dengan catatan spesifik.";
        }

        if (str_contains($clean, 'validasi data') || str_contains($clean, 'validasi data warga') || str_contains($clean, 'ai check') || str_contains($clean, 'ocr')) {
            return "🤖 **Validasi Data Warga & AI Document Check:**\n\n"
                ."• **Pencocokan Data**: Pastikan 16 digit NIK dan No KK pada formulir persis sama dengan dokumen fisik.\n"
                ."• **Indikator AI Check**:\n"
                ."  - `DATA_SESUAI`: Data formulir cocok dengan OCR dokumen.\n"
                ."  - `DATA_PERLU_DICEK`: Ada kemungkinan typo nama, NIK, atau beda alamat.\n"
                ."  - `DOKUMEN_TIDAK_TERBACA`: Foto buram atau miring.\n\n"
                ."⚠️ *AI hanya alat bantu rekomendasi. Keputusan akhir mutlak dilakukan oleh petugas desa.*";
        }

        if (str_contains($clean, 'daftar pengajuan baru') || str_contains($clean, 'pengajuan baru') || str_contains($clean, 'status')) {
            return "📥 **Pengelolaan Daftar Pengajuan Baru (SUBMITTED):**\n\n"
                ."• Setiap pengajuan warga yang baru dikirim akan masuk ke tab *Pengajuan Baru* dengan status `SUBMITTED`.\n"
                ."• Saat Staff membuka halaman detail pengajuan, status otomatis berubah menjadi `UNDER_REVIEW` untuk mencegah dobel pemeriksaan antar-petugas.\n"
                ."• Pastikan memproses permohonan sesuai urutan waktu masuk (*First In First Out*).";
        }

        if (str_contains($clean, 'perbaikan') || str_contains($clean, 'need revision') || str_contains($clean, 'catatan')) {
            return "📝 **Panduan Catatan Perbaikan (NEED_REVISION):**\n\n"
                ."Tuliskan catatan yang jelas dan sopan untuk pemohon. Contoh: *\"Foto e-KTP buram pada bagian NIK. Mohon unggah ulang foto e-KTP asli yang jelas di pencahayaan cukup.\"*\n\n"
                ."Catatan akan otomatis muncul di notifikasi dan dashboard warga pemohon.";
        }

        if (str_contains($clean, 'supervisor') || str_contains($clean, 'teruskan') || str_contains($clean, 'verifikasi')) {
            return "✅ **Kriteria Meneruskan Berkas ke Supervisor:**\n\n"
                ."Berkas diteruskan (`VERIFIED_BY_STAFF`) apabila seluruh berkas identitas KTP & KK telah terverifikasi sah dan pemohon terdaftar sebagai warga Desa Sukamaju.";
        }

        return "Sebagai Asisten Staff Administrasi, saya dapat membantu Anda memahami SOP pemeriksaan dokumen, validasi data warga, penanganan pengajuan baru, dan rekomendasi AI Document Check. Ada kasus berkas tertentu yang ingin dikonsultasikan?";
    }

    /**
     * Basis Pengetahuan Khusus Role SUPERVISOR.
     */
    protected static function findSupervisorAnswer(string $clean): ?string
    {
        if (str_contains($clean, 'analisis kelayakan') || str_contains($clean, 'kelayakan') || str_contains($clean, 'indikator')) {
            return "⚖️ **Analisis Kelayakan Pengajuan SKTM (Supervisor):**\n\n"
                ."Supervisor (Kasi Pelayanan) mengevaluasi kelayakan faktual ekonomi pemohon:\n"
                ."1. **Tingkat Penghasilan**: Total pendapatan keluarga di bawah garis kemiskinan daerah / UMR.\n"
                ."2. **Beban Tanggungan**: Jumlah anak usia sekolah, balita, lansia, atau anggota keluarga disabilitas.\n"
                ."3. **Kondisi Hunian**: Status rumah tinggal (menumpang/sewa/tanah kas desa) serta kondisi fisik bangunan.\n"
                ."4. **Tujuan Surat**: Keperluan mendesak seperti keringanan rawat inap RS atau beasiswa/UKT pendidikan.";
        }

        if (str_contains($clean, 'pemeriksaan hasil verifikasi') || str_contains($clean, 'hasil verifikasi') || str_contains($clean, 'staff')) {
            return "🔍 **Pemeriksaan Hasil Verifikasi Staff:**\n\n"
                ."Supervisor memastikan bahwa:\n"
                ."• Staff telah memvalidasi keaslian dokumen e-KTP dan KK pemohon.\n"
                ."• Catatan rekomendasi AI Document Check telah dikonfirmasi valid oleh Staff.\n"
                ."• Jika ditemukan cacat administrasi yang lolos, Supervisor dapat mengembalikan berkas ke status `NEED_REVISION`.";
        }

        if (str_contains($clean, 'menunggu persetujuan') || str_contains($clean, 'pengajuan menunggu persetujuan') || str_contains($clean, 'teruskan') || str_contains($clean, 'kades')) {
            return "📤 **Pengajuan Menunggu Persetujuan & Rekomendasi ke Kades:**\n\n"
                ."• Jika pemohon terbukti layak, Supervisor mengklik tombol **Verifikasi & Setujui Kelayakan** (`VERIFIED_BY_SUPERVISOR`).\n"
                ."• Berkas akan langsung berpindah ke antrian persetujuan akhir (*Approval Hub*) Kepala Desa.\n"
                ."• Jika tidak layak, klik **Tolak Permohonan (REJECTED)** dengan alasan objektif.";
        }

        if (str_contains($clean, 'tolak') || str_contains($clean, 'rejected')) {
            return "🚫 **Pedoman Penolakan Permohonan (REJECTED):**\n\n"
                ."Permohonan berhak ditolak jika pemohon terbukti mampu secara finansial atau ditemukan indikasi pemalsuan data. Setiap penolakan dicatat permanen dalam audit log sistem desa.";
        }

        return "Sebagai Asisten Supervisor, saya dapat membantu Anda menganalisis kelayakan ekonomi pemohon, mengevaluasi hasil verifikasi staff, dan memandu SOP penerusan berkas ke Kepala Desa.";
    }

    /**
     * Basis Pengetahuan Khusus Role KEPALA DESA.
     */
    protected static function findKadesAnswer(string $clean): ?string
    {
        if (str_contains($clean, 'ringkasan pelayanan') || str_contains($clean, 'ringkasan')) {
            return "📊 **Ringkasan Pelayanan Surat Desa:**\n\n"
                ."Pemerintah Desa Sukamaju menerapkan alur pelayanan terpadu 5 tahapan:\n"
                ."• Seluruh berkas yang sampai ke meja Kepala Desa telah melewati verifikasi administrasi Staff dan uji kelayakan Kasi Pelayanan (Supervisor).\n"
                ."• Kepala Desa bertindak sebagai pemegang otoritas legalitas tunggal untuk persetujuan akhir (approval) dan pengesahan surat resmi.\n"
                ."• Anda dapat memantau permohonan aktif melalui menu Persetujuan SKTM dan Ringkasan Statistik.";
        }

        if (str_contains($clean, 'menunggu approval') || str_contains($clean, 'approval') || str_contains($clean, 'persetujuan')) {
            return "🏛️ **Pengajuan Menunggu Approval & Legalisasi Surat:**\n\n"
                ."• Di menu *Persetujuan SKTM*, Anda dapat melihat seluruh berkas yang siap disahkan.\n"
                ."• Anda dapat melihat pratinjau draf berkas sebelum menyetujui.\n"
                ."• Ketika Anda menekan **Setujui (APPROVE)**, sistem secara instan menghasilkan nomor surat resmi unik berurutan, tanda tangan/stempel digital, dan berkas PDF resmi ber-QRCode.";
        }

        if (str_contains($clean, 'statistik') || str_contains($clean, 'statistik surat')) {
            return "📈 **Statistik Surat Desa & Performa Pelayanan:**\n\n"
                ."• **Rasio Persetujuan**: Menampilkan persentase permohonan yang disetujui vs ditolak.\n"
                ."• **Rata-rata Waktu**: Target penyelesaian penerbitan surat adalah 1 hingga 3 hari kerja.\n"
                ."• **Total Dokumen Terbit**: Akumulasi surat yang telah resmi disahkan pada tahun berjalan.";
        }

        if (str_contains($clean, 'nomor surat') || str_contains($clean, 'penomoran')) {
            return "🔢 **Format Penomoran Surat Otomatis:**\n\n"
                ."Format resmi: `470 / [NOMOR_URUT] / SKTM / [BULAN_ROMAWI] / [TAHUN]`\n"
                ."Nomor registrasi digenerate secara otomatis oleh sistem, anti-duplikasi, dan tercatat permanen.";
        }

        return "Sebagai Asisten Eksekutif Kepala Desa, saya siap membantu Anda menelaah ringkasan pelayanan, memantau permohonan menunggu approval, serta melihat statistik penerbitan surat desa.";
    }

    /**
     * Basis Pengetahuan Khusus Role ADMINISTRATOR.
     */
    protected static function findAdminAnswer(string $clean): ?string
    {
        if (str_contains($clean, 'manajemen pengguna') || str_contains($clean, 'kelola pengguna') || str_contains($clean, 'user')) {
            return "👥 **Manajemen Pengguna & Pengaturan Akun:**\n\n"
                ."• Administrator dapat mengaktifkan atau menonaktifkan pengguna melalui menu *Manajemen Pengguna*.\n"
                ."• Pengguna yang dinonaktifkan (`is_active = false`) tidak dapat masuk ke sistem.\n"
                ."• Password di-hash menggunakan algoritma Bcrypt (12 rounds) dengan proteksi data pribadi.";
        }

        if (str_contains($clean, 'pengaturan sistem') || str_contains($clean, 'master data') || str_contains($clean, 'konfigurasi')) {
            return "⚙️ **Pengaturan Sistem & Master Data Desa:**\n\n"
                ."• **Kop Surat & Identitas**: Diatur pada environment `VILLAGE_NAME`, `VILLAGE_KECAMATAN`, `VILLAGE_KABUPATEN`.\n"
                ."• **Driver AI & OCR**: Dikonfigurasi melalui `AI_DOCUMENT_CHECKER_DRIVER` dan `AI_CHAT_DRIVER`.\n"
                ."• **Keamanan**: Proteksi CSRF, Session Cookie terenkripsi, dan Rate Limiting aktif.";
        }

        if (str_contains($clean, 'monitoring aktivitas') || str_contains($clean, 'audit') || str_contains($clean, 'log')) {
            return "🔍 **Monitoring Aktivitas & Audit Trail:**\n\n"
                ."• Seluruh pergerakan status permohonan tercatat di tabel `sktm_status_histories` lengkap dengan ID petugas, role, waktu eksekusi, dan catatan.\n"
                ."• Dokumen fisik disimpan pada *private storage* lokal terproteksi authorization.";
        }

        if (str_contains($clean, 'rbac') || str_contains($clean, 'role')) {
            return "🛡️ **Struktur Role-Based Access Control (RBAC):**\n\n"
                ."SIPELDES memiliki 5 peran independen: **Masyarakat, Staff, Supervisor, Kepala Desa, dan Administrator** dengan matriks wewenang terpisah dan teruji aman.";
        }

        return "Saya adalah AI Assistant SIPEDES (Sistem Pelayanan Desa Digital), asisten kecerdasan buatan yang dirancang untuk membantu Anda dalam menggunakan dan mengelola sistem pelayanan desa. Saya siap memberikan informasi, panduan, serta bantuan sesuai dengan hak akses dan peran Anda sebagai Administrator.";
    }

    /**
     * Respon fallback santun jika pertanyaan belum tercakup dalam Knowledge Base.
     * Role diambil dari objek User (backend) – aman dari manipulasi frontend.
     */
    public static function getRoleFallbackResponse(User $user): string
    {
        $role = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;

        if ($role === 'masyarakat') {
            return VillageKnowledgeBase::getFallbackResponse();
        }

        return "Mohon maaf, informasi terkait pertanyaan tersebut belum tersedia di basis panduan internal SIPEDES untuk peran Anda. "
            ."Silakan berkonsultasi langsung dengan Sekretaris Desa atau Tim Administrator Sistem di kantor desa Sukamaju.";
    }
}

