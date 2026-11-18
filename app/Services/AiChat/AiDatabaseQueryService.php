<?php

namespace App\Services\AiChat;

use App\Enums\SktmStatus;
use App\Enums\UserRole;
use App\Models\SktmApplication;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiDatabaseQueryService
{
    /**
     * Memeriksa dan mengeksekusi pertanyaan berbasis identitas, tugas, dan query database riil.
     * Mengembalikan string jawaban jika pertanyaan dikenali, atau null jika perlu diproses oleh knowledge base/LLM umum.
     */
    public function resolveQuery(string $message, ?User $user = null): ?string
    {
        $normalized = strtolower(trim($message));
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalized);
        $clean = preg_replace('/\s+/', ' ', trim($clean));

        // 1. Permintaan Coding Python / Pemrograman Luar Sistem (BAGIAN 6)
        if ($this->isCodingRequest($clean)) {
            return "Maaf, saya tidak menyediakan pembuatan kode Python. Saya dapat membantu menjelaskan fitur dan penggunaan sistem SIPEDES.";
        }

        // 2. Pertanyaan Identitas AI ("Siapa Anda?", "Siapa kamu?") (BAGIAN 2 A)
        if ($this->isAiIdentityQuestion($clean)) {
            return "Saya adalah AI Assistant SIPEDES, asisten kecerdasan buatan yang membantu pengguna dalam sistem pelayanan desa.";
        }

        // Jika tidak login, kembalikan null agar ditangani guest handler
        if (! $user) {
            return null;
        }

        $role = $user->role instanceof UserRole ? $user->role : UserRole::from((string) $user->role);
        $userName = !empty($user->name) ? trim($user->name) : null;

        // 3. Pertanyaan Identitas Pengguna ("Siapa saya?", "Siapa nama saya?", "Saya login sebagai siapa?") (BAGIAN 2 B)
        if ($this->isUserIdentityQuestion($clean)) {
            $nameStr = $userName ?? 'belum tersedia di sistem';
            $roleLabel = $role->label();

            return "Nama Anda {$nameStr}. Anda login sebagai {$roleLabel} SIPEDES.";
        }

        // 4. Pertanyaan Tugas & Wewenang Pengguna ("Apa tugas saya?", "Apa wewenang saya?") (BAGIAN 2 C)
        if ($this->isUserDutiesQuestion($clean)) {
            return $this->getRoleDutiesResponse($role);
        }

        // 5. Pertanyaan Database & Statistik Aktual Sistem (BAGIAN 3 & 4 & 5)
        return $this->resolveDatabaseStatsQuery($clean, $user, $role);
    }

    /**
     * Deteksi permintaan pembuatan kode Python atau coding di luar SIPEDES.
     */
    protected function isCodingRequest(string $text): bool
    {
        $patterns = [
            'coding python', 'koding python', 'buatkan coding python', 'buatkan kode python',
            'script python', 'program python', 'buatkan program python', 'coding java',
            'coding c++', 'coding php', 'coding javascript', 'buat script', 'buatkan kode',
            'buatkan program', 'buatkan fungsi', 'buatkan function', 'tulis kode',
            'tuliskan kode', 'codingan',
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($text, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Deteksi pertanyaan identitas AI ("Siapa Anda?", "Kamu siapa?", dll).
     */
    protected function isAiIdentityQuestion(string $text): bool
    {
        // Jangan tangkap jika bertanya tentang identitas pengguna sendiri
        if ($this->isUserIdentityQuestion($text) || $this->isUserDutiesQuestion($text)) {
            return false;
        }

        $patterns = [
            'siapa anda', 'siapa kamu', 'kamu siapa', 'anda siapa', 'kamu ini siapa',
            'anda ini siapa', 'siapakah anda', 'siapakah kamu', 'apa itu ai assistant sipedes',
            'kamu itu siapa', 'nama kamu apa', 'siapa nama kamu', 'siapa nama anda',
            'nama anda siapa', 'kamu adalah siapa',
        ];

        foreach ($patterns as $pattern) {
            if ($text === $pattern || str_starts_with($text, $pattern) || str_contains($text, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Deteksi pertanyaan identitas pengguna ("Siapa saya?", "Saya login sebagai apa?").
     */
    protected function isUserIdentityQuestion(string $text): bool
    {
        $patterns = [
            'siapa saya', 'siapa nama saya', 'nama saya siapa', 'saya siapa',
            'saya login sebagai siapa', 'saya login sebagai apa', 'saya masuk sebagai apa',
            'apa role saya', 'role saya apa', 'apa jabatan saya', 'jabatan saya apa',
            'apa peran saya', 'peran saya apa', 'identitas saya', 'profil saya',
            'akun saya', 'siapakah saya', 'saya ini siapa',
        ];

        foreach ($patterns as $pattern) {
            if ($text === $pattern || str_starts_with($text, $pattern) || str_contains($text, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Deteksi pertanyaan tugas & wewenang pengguna ("Apa tugas saya?", "Apa yang bisa saya lakukan?").
     */
    protected function isUserDutiesQuestion(string $text): bool
    {
        $patterns = [
            'apa tugas saya', 'tugas saya apa', 'tugas saya', 'apa pekerjaan saya',
            'pekerjaan saya apa', 'apa wewenang saya', 'wewenang saya apa', 'wewenang saya',
            'apa kewenangan saya', 'kewenangan saya apa', 'apa hak akses saya', 'hak akses saya',
            'apa yang dapat saya lakukan', 'apa yang bisa saya lakukan', 'bisa apa saja saya',
            'fungsi saya di sistem', 'peran saya di sistem',
        ];

        foreach ($patterns as $pattern) {
            if ($text === $pattern || str_starts_with($text, $pattern) || str_contains($text, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Deskripsi tugas resmi dan wewenang pengguna berdasarkan 5 role SIPEDES.
     */
    protected function getRoleDutiesResponse(UserRole $role): string
    {
        return match ($role) {
            UserRole::MASYARAKAT => "Sebagai Masyarakat (Warga), tugas dan hak akses Anda di SIPEDES adalah:\n"
                ."1. Mengajukan surat pelayanan desa (SKTM dan surat administrasi lainnya).\n"
                ."2. Mengunggah berkas persyaratan seperti KTP, Kartu Keluarga, dan Surat Pengantar RT/RW.\n"
                ."3. Memantau riwayat dan progres status verifikasi pengajuan surat secara transparan.\n"
                ."4. Memperbaiki berkas jika diminta perbaikan oleh petugas desa (status Perlu Perbaikan).\n"
                ."5. Mengunduh dan mencetak surat resmi yang telah disetujui dan diterbitkan.",

            UserRole::STAFF => "Sebagai Staff Pelayanan Desa, tugas dan wewenang Anda di SIPEDES adalah:\n"
                ."1. Memeriksa kelengkapan berkas fisik dan digital pengajuan surat dari masyarakat.\n"
                ."2. Meninjau rekomendasi hasil pemeriksaan otomatis AI Document Check.\n"
                ."3. Meminta perbaikan berkas kepada pemohon jika dokumen belum lengkap (NEED_REVISION).\n"
                ."4. Melakukan verifikasi awal berkas yang lengkap dan meneruskannya ke Supervisor (VERIFIED_BY_STAFF).",

            UserRole::SUPERVISOR => "Sebagai Supervisor / Kasi Pelayanan, tugas dan wewenang Anda di SIPEDES adalah:\n"
                ."1. Melakukan verifikasi lanjutan dan uji kelayakan ekonomi atas pengajuan yang telah diperiksa Staff.\n"
                ."2. Memeriksa kesesuaian data pemohon dengan kriteria bantuan/keringanan desa.\n"
                ."3. Mengembalikan berkas jika ditemukan ketidaksesuaian administrasi atau lapangan.\n"
                ."4. Memberikan verifikasi kelayakan dan meneruskannya ke Kepala Desa (VERIFIED_BY_SUPERVISOR).",

            UserRole::KEPALA_DESA => "Sebagai Kepala Desa, tugas dan wewenang Anda di SIPEDES adalah:\n"
                ."1. Meninjau permohonan surat yang telah lolos verifikasi berjenjang dari Staff dan Supervisor.\n"
                ."2. Memberikan persetujuan akhir (Approval) pengesahan surat resmi desa.\n"
                ."3. Memberikan penolakan pengajuan surat disertai alasan resmi jika tidak memenuhi kriteria.\n"
                ."4. Memantau statistik pelayanan, penerbitan surat, dan kinerja pelayanan desa Sukamaju.",

            UserRole::ADMIN => "Sebagai Administrator, tugas dan wewenang Anda di SIPEDES adalah:\n"
                ."1. Mengelola akun pengguna (manajemen peran, profil, serta aktivasi/nonaktivasi akun aparat & warga).\n"
                ."2. Mengelola konfigurasi master data desa, jam operasional layanan, dan kebijakan keamanan sistem.\n"
                ."3. Mengonfigurasi integrasi layanan eksternal (WhatsApp Gateway FONTE & AI Assistant SIPEDES).\n"
                ."4. Memantau statistik database kependudukan, log aktivitas sistem, dan integritas data pelayanan.",
        };
    }

    /**
     * Memproses pertanyaan data statistik dari database aktual dengan otorisasi role ketat.
     */
    protected function resolveDatabaseStatsQuery(string $text, User $user, UserRole $role): ?string
    {
        try {
            // A. Pertanyaan Jumlah Total Pengguna Terdaftar
            if ($this->matchesAny($text, [
                'berapa jumlah pengguna', 'jumlah pengguna terdaftar', 'total pengguna',
                'berapa pengguna terdaftar', 'jumlah user terdaftar', 'berapa total user',
                'berapa akun terdaftar', 'jumlah seluruh pengguna', 'berapa banyak pengguna',
            ])) {
                if ($role === UserRole::MASYARAKAT) {
                    return "Maaf, informasi statistik seluruh pengguna merupakan data internal administrasi desa. Anda dapat menanyakan informasi terkait status pengajuan surat Anda sendiri.";
                }

                $count = User::count();
                return "Saat ini terdapat {$count} pengguna terdaftar di SIPEDES.";
            }

            // B. Pertanyaan Jumlah Pengguna Berdasarkan Role Tertentu
            if ($this->matchesAny($text, ['jumlah administrator', 'berapa administrator', 'berapa admin', 'jumlah admin'])) {
                if ($role === UserRole::MASYARAKAT) {
                    return "Maaf, rincian data pengguna sistem dibatasi hanya untuk aparatur desa.";
                }
                $count = User::where('role', UserRole::ADMIN)->count();
                return "Saat ini terdapat {$count} pengguna dengan role Administrator di SIPEDES.";
            }

            if ($this->matchesAny($text, ['jumlah staff', 'berapa staff', 'berapa staf', 'jumlah staf'])) {
                if ($role === UserRole::MASYARAKAT) {
                    return "Maaf, rincian data pengguna sistem dibatasi hanya untuk aparatur desa.";
                }
                $count = User::where('role', UserRole::STAFF)->count();
                return "Saat ini terdapat {$count} pengguna dengan role Staff Pelayanan di SIPEDES.";
            }

            if ($this->matchesAny($text, ['jumlah supervisor', 'berapa supervisor', 'kasi pelayanan'])) {
                if ($role === UserRole::MASYARAKAT) {
                    return "Maaf, rincian data pengguna sistem dibatasi hanya untuk aparatur desa.";
                }
                $count = User::where('role', UserRole::SUPERVISOR)->count();
                return "Saat ini terdapat {$count} pengguna dengan role Supervisor di SIPEDES.";
            }

            if ($this->matchesAny($text, ['jumlah kepala desa', 'berapa kepala desa', 'berapa kades', 'jumlah kades'])) {
                if ($role === UserRole::MASYARAKAT) {
                    return "Maaf, rincian data pengguna sistem dibatasi hanya untuk aparatur desa.";
                }
                $count = User::where('role', UserRole::KEPALA_DESA)->count();
                return "Saat ini terdapat {$count} pengguna dengan role Kepala Desa di SIPEDES.";
            }

            if ($this->matchesAny($text, ['jumlah masyarakat', 'berapa masyarakat', 'berapa warga', 'jumlah warga', 'jumlah akun masyarakat', 'berapa akun masyarakat', 'berapa pengguna masyarakat', 'pengguna masyarakat'])) {
                if ($role === UserRole::MASYARAKAT) {
                    return "Maaf, rincian data pengguna sistem dibatasi hanya untuk aparatur desa.";
                }
                $count = User::where('role', UserRole::MASYARAKAT)->count();
                return "Saat ini terdapat {$count} pengguna dengan role Masyarakat (Warga) di SIPEDES.";
            }

            // C. Pertanyaan Jumlah Pengajuan Hari Ini
            if ($this->matchesAny($text, [
                'pengajuan hari ini', 'berapa pengajuan hari ini', 'surat masuk hari ini',
                'berapa surat hari ini', 'jumlah surat hari ini',
            ])) {
                $todayDateStr = now()->translatedFormat('d F Y');
                if ($role === UserRole::MASYARAKAT) {
                    $count = SktmApplication::where('user_id', $user->id)
                        ->whereDate('created_at', today())
                        ->count();
                    return "Anda mengajukan {$count} surat pada hari ini ({$todayDateStr}).";
                }

                $count = SktmApplication::whereDate('created_at', today())->count();
                return "Jumlah pengajuan surat yang masuk pada hari ini ({$todayDateStr}) adalah {$count} berkas.";
            }

            // D. Pertanyaan Jumlah Pengajuan Selesai / Disetujui
            if ($this->matchesAny($text, [
                'pengajuan selesai', 'pengajuan yang telah selesai', 'surat yang sudah selesai',
                'berapa pengajuan selesai', 'jumlah pengajuan disetujui', 'berapa surat disetujui',
                'surat yang disetujui', 'pengajuan yang disetujui', 'berapa surat yang sudah selesai',
            ])) {
                $approvedStatuses = [SktmStatus::APPROVED->value, SktmStatus::APPROVED_KADES->value];
                if ($role === UserRole::MASYARAKAT) {
                    $count = SktmApplication::where('user_id', $user->id)
                        ->whereIn('status', $approvedStatuses)
                        ->count();
                    return "Anda memiliki {$count} pengajuan surat yang telah selesai dan disetujui.";
                }

                $count = SktmApplication::whereIn('status', $approvedStatuses)->count();
                return "Saat ini terdapat {$count} pengajuan surat yang telah selesai dan disetujui di SIPEDES.";
            }

            // E. Pertanyaan Jumlah Pengajuan Ditolak
            if ($this->matchesAny($text, [
                'pengajuan ditolak', 'berapa pengajuan ditolak', 'surat yang ditolak',
                'jumlah pengajuan yang ditolak', 'berapa surat ditolak',
            ])) {
                if ($role === UserRole::MASYARAKAT) {
                    $count = SktmApplication::where('user_id', $user->id)
                        ->where('status', SktmStatus::REJECTED->value)
                        ->count();
                    return "Anda memiliki {$count} pengajuan surat yang berstatus ditolak.";
                }

                $count = SktmApplication::where('status', SktmStatus::REJECTED->value)->count();
                return "Saat ini terdapat {$count} pengajuan surat yang ditolak di SIPEDES.";
            }

            // F. Pertanyaan Jumlah Pengajuan yang Sedang Diproses
            if ($this->matchesAny($text, [
                'pengajuan diproses', 'sedang diproses', 'berapa pengajuan yang sedang diproses',
                'surat yang sedang diproses', 'jumlah pengajuan proses',
            ])) {
                $inProgressStatuses = [
                    SktmStatus::SUBMITTED->value,
                    SktmStatus::UNDER_REVIEW->value,
                    SktmStatus::STAFF_REVIEW->value,
                    SktmStatus::VERIFIED_BY_STAFF->value,
                    SktmStatus::VERIFIED_BY_SUPERVISOR->value,
                    SktmStatus::VERIFIED_SUPERVISOR->value,
                ];

                if ($role === UserRole::MASYARAKAT) {
                    $count = SktmApplication::where('user_id', $user->id)
                        ->whereIn('status', $inProgressStatuses)
                        ->count();
                    return "Anda memiliki {$count} pengajuan surat yang sedang dalam proses pelayanan.";
                }

                $count = SktmApplication::whereIn('status', $inProgressStatuses)->count();
                return "Saat ini terdapat {$count} pengajuan surat yang sedang dalam proses pelayanan di SIPEDES.";
            }

            // G. Pertanyaan Total Seluruh Pengajuan Surat
            if ($this->matchesAny($text, [
                'berapa jumlah pengajuan surat', 'berapa jumlah pengajuan', 'total pengajuan surat',
                'jumlah seluruh pengajuan', 'total permohonan surat', 'berapa surat masuk',
            ])) {
                if ($role === UserRole::MASYARAKAT) {
                    $count = SktmApplication::where('user_id', $user->id)->count();
                    return "Total pengajuan surat yang telah Anda ajukan di SIPEDES adalah {$count} berkas.";
                }

                $count = SktmApplication::count();
                return "Total pengajuan surat yang tercatat di SIPEDES saat ini adalah {$count} berkas.";
            }

            // H. Pertanyaan Riwayat / Status Pengajuan Pribadi (Masyarakat)
            // Hanya aktif untuk cek status aktual, bukan pertanyaan panduan ("bagaimana cara...") atau edukasi ("mengapa...")
            if (! str_contains($text, 'cara') && ! str_contains($text, 'mengapa') && ! str_contains($text, 'kenapa')) {
                if ($this->matchesAny($text, [
                    'status pengajuan surat saya', 'status pengajuan saya', 'status surat saya',
                    'bagaimana status pengajuan', 'bagaimana status surat',
                    'cek pengajuan saya', 'cek status surat saya', 'cek status pengajuan', 'status berkas saya',
                ])) {
                    if ($role !== UserRole::MASYARAKAT) {
                        return "Fitur pengecekan pengajuan pribadi dikhususkan untuk akun pemohon (Masyarakat). Sebagai aparatur desa, Anda dapat memantau permohonan melalui menu antrian verifikasi.";
                    }

                    $latestApps = SktmApplication::where('user_id', $user->id)
                        ->latest()
                        ->take(3)
                        ->get();

                    if ($latestApps->isEmpty()) {
                        return "Anda belum memiliki pengajuan surat di SIPEDES. Anda dapat membuat pengajuan baru melalui menu 'Buat Pengajuan'.";
                    }

                    $reply = "Berikut adalah ringkasan pengajuan surat Anda:\n";
                    foreach ($latestApps as $idx => $app) {
                        $no = $idx + 1;
                        $statusLabel = $app->status instanceof SktmStatus ? $app->status->label() : SktmStatus::tryFrom((string)$app->status)?->label() ?? (string)$app->status;
                        $tgl = $app->created_at ? $app->created_at->format('d/m/Y') : '-';
                        $reply .= "{$no}. No. {$app->nomor_pengajuan} ({$tgl}) - Status: **{$statusLabel}**\n";
                    }
                    return trim($reply);
                }
            }

            return null;
        } catch (Throwable $e) {
            Log::error('Kesalahan query database AI: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'query' => $text,
            ]);

            return "Maaf, saat ini saya belum dapat mengakses data SIPEDES. Silakan coba kembali beberapa saat lagi.";
        }
    }

    /**
     * Helper cek apakah teks mengandung salah satu pola kata kunci.
     *
     * @param list<string> $patterns
     */
    protected function matchesAny(string $text, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (str_contains($text, $pattern)) {
                return true;
            }
        }

        return false;
    }
}

