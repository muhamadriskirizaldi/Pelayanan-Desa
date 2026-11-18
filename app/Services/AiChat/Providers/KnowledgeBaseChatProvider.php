<?php

namespace App\Services\AiChat\Providers;

use App\Models\User;
use App\Services\AiChat\AiDatabaseQueryService;
use App\Services\AiChat\Contracts\AiChatProviderInterface;
use App\Services\AiChat\RoleKnowledgeBase;
use App\Services\AiChat\VillageKnowledgeBase;

/**
 * Knowledge Base Chat Provider – SIPEDES AI Assistant.
 *
 * Prinsip keamanan:
 * - Role pengguna SELALU diambil dari objek $user (Auth::user() di backend).
 * - Tidak ada role yang dikirim atau dipercaya dari input frontend/client.
 * - Validasi otorisasi dilakukan di Controller dan Middleware, bukan di sini.
 */
class KnowledgeBaseChatProvider implements AiChatProviderInterface
{
    public function __construct(
        protected ?AiDatabaseQueryService $dbQueryService = null
    ) {
        $this->dbQueryService = $dbQueryService ?? new AiDatabaseQueryService();
    }

    /**
     * Memproses pertanyaan pengguna dan menghasilkan respons berbasis
     * Database Riil dan Knowledge Base terintegrasi Role SIPEDES.
     *
     * @param string    $message  Pesan yang dikirim pengguna.
     * @param array     $history  Riwayat percakapan (maks. 8 pesan terakhir).
     * @param User|null $user     Objek pengguna yang terautentikasi (dari backend Auth).
     */
    public function reply(string $message, array $history = [], ?User $user = null): string
    {
        // =========================================================
        // PRIORITAS UTAMA: Query Database Riil, Identitas, Tugas, dan Anti-Halusinasi
        // =========================================================
        $resolved = $this->dbQueryService->resolveQuery($message, $user);
        if ($resolved !== null) {
            return $resolved;
        }

        // =========================================================
        // PATH A: Pengguna terautentikasi – gunakan Role Knowledge Base.
        // Role diambil SEPENUHNYA dari objek $user (backend Auth::user()).
        // =========================================================
        if ($user !== null) {
            // A1. Pertanyaan identitas AI
            if (RoleKnowledgeBase::isIdentityQuestion($message)) {
                $role = $user->role->value ?? 'masyarakat';
                return RoleKnowledgeBase::getIdentityResponse($role, $user->name);
            }

            // A2. Pertanyaan tugas AI
            if (RoleKnowledgeBase::isTaskQuestion($message)) {
                $role = $user->role->value ?? 'masyarakat';
                return RoleKnowledgeBase::getTaskResponse($role);
            }

            // A3. Penolakan permintaan coding di luar SIPEDES
            $rejection = RoleKnowledgeBase::getOutOfScopeRejection($message);
            if ($rejection !== null) {
                return $rejection;
            }

            // A4. Cari jawaban berbasis Knowledge Base sesuai role aktif
            $answer = RoleKnowledgeBase::findAnswerForUser($message, $user);
            if ($answer !== null) {
                return $answer;
            }

            // A5. Fallback role-aware jika tidak ada jawaban yang cocok
            return RoleKnowledgeBase::getRoleFallbackResponse($user);
        }

        // =========================================================
        // PATH B: Pengguna tidak login (tamu) – Knowledge Base umum.
        // =========================================================
        $normalized = strtolower(trim($message));

        // B1. Identitas AI untuk tamu
        if (RoleKnowledgeBase::isIdentityQuestion($message)) {
            return "Saya adalah **AI Assistant SIPEDES** (Sistem Pelayanan Desa Digital). "
                ."Saya merupakan asisten kecerdasan buatan yang dirancang untuk membantu pengguna "
                ."dalam mengakses informasi, memahami prosedur, dan menggunakan layanan administrasi desa.\n\n"
                ."Silakan **masuk (login)** ke akun Anda agar saya dapat memberikan bantuan yang lebih personal "
                ."sesuai dengan peran dan hak akses Anda.";
        }

        // B2. Penolakan coding untuk tamu
        $rejection = RoleKnowledgeBase::getOutOfScopeRejection($message);
        if ($rejection !== null) {
            return $rejection;
        }

        // B3. Salam ramah untuk tamu
        if (preg_match('/^(halo|hai|pagi|siang|sore|malam|assalamu[\w\s]+|permisi|tes|hei|hey)/i', $normalized)) {
            return "Halo! Selamat datang di **SIPEDES** (Sistem Pelayanan Desa Digital) Desa Sukamaju.\n\n"
                ."Saya adalah **AI Assistant SIPEDES**, siap membantu Anda dengan informasi pelayanan administrasi desa. "
                ."Ada yang bisa saya bantu?";
        }

        // B4. Cari jawaban di Village Knowledge Base umum
        $answer = VillageKnowledgeBase::findAnswer($message);
        if ($answer !== null) {
            return $answer;
        }

        return VillageKnowledgeBase::getFallbackResponse();
    }
}
