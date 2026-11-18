<?php

namespace App\Services\AiChat\Contracts;

use App\Models\User;

interface AiChatProviderInterface
{
    /**
     * Menghasilkan jawaban atas pertanyaan masyarakat seputar pelayanan desa.
     *
     * @param  string  $message  Pertanyaan yang diajukan pengguna.
     * @param  array<array{role: string, content: string}>  $history  Riwayat percakapan sebelumnya.
     * @param  User|null  $user  Akun pengguna masyarakat yang bertanya.
     * @return string Jawaban resmi dari asisten AI.
     */
    public function reply(string $message, array $history = [], ?User $user = null): string;
}
