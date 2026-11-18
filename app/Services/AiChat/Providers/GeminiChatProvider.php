<?php

namespace App\Services\AiChat\Providers;

use App\Models\User;
use App\Services\AiChat\AiDatabaseQueryService;
use App\Services\AiChat\Contracts\AiChatProviderInterface;
use App\Services\AiChat\VillageKnowledgeBase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeminiChatProvider implements AiChatProviderInterface
{
    public function __construct(
        protected KnowledgeBaseChatProvider $fallbackProvider,
        protected ?AiDatabaseQueryService $dbQueryService = null
    ) {
        $this->dbQueryService = $dbQueryService ?? new AiDatabaseQueryService();
    }

    /**
     * Memproses pesan menggunakan LLM Google Gemini dengan panduan ketat basis data desa.
     */
    public function reply(string $message, array $history = [], ?User $user = null): string
    {
        // =========================================================
        // PRIORITAS UTAMA: Identitas, Tugas, dan Query Database Riil (Anti-Halusinasi)
        // =========================================================
        $resolved = $this->dbQueryService->resolveQuery($message, $user);
        if ($resolved !== null) {
            return $resolved;
        }

        $apiKey = config('ai.api_key');

        if (empty($apiKey)) {
            return $this->fallbackProvider->reply($message, $history, $user);
        }

        try {
            $model = config('ai.model', 'gemini-1.5-flash');
            $timeout = (int) config('ai.timeout', 15);
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            // Format riwayat percakapan untuk Gemini
            $contents = [];

            // Tambahkan konteks riwayat sebelumnya (maksimal 6 percakapan terakhir)
            $recentHistory = array_slice($history, -6);
            foreach ($recentHistory as $msg) {
                $role = ($msg['role'] ?? '') === 'user' ? 'user' : 'model';
                $contents[] = [
                    'role' => $role,
                    'parts' => [
                        ['text' => $msg['content'] ?? ''],
                    ],
                ];
            }

            // Tambahkan pertanyaan saat ini
            $contents[] = [
                'role' => 'user',
                'parts' => [
                    ['text' => $message],
                ],
            ];

            $response = Http::timeout($timeout)->post($url, [
                'system_instruction' => [
                    'parts' => [
                        ['text' => VillageKnowledgeBase::getSystemPrompt()],
                    ],
                ],
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => 0.2,
                    'maxOutputTokens' => 800,
                ],
            ]);

            if ($response->successful()) {
                $replyText = $response->json('candidates.0.content.parts.0.text');
                if (! empty($replyText)) {
                    return trim($replyText);
                }
            }

            Log::warning('Respon API Gemini Chat kosong atau tidak berhasil: '.$response->body());
        } catch (Throwable $e) {
            Log::error('Terjadi kesalahan pada Gemini Chat Provider: '.$e->getMessage());
        }

        return $this->fallbackProvider->reply($message, $history, $user);
    }
}
