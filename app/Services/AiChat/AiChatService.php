<?php

namespace App\Services\AiChat;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\AiChat\Contracts\AiChatProviderInterface;
use App\Services\AiChat\Providers\GeminiChatProvider;
use App\Services\AiChat\Providers\KnowledgeBaseChatProvider;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;

class AiChatService
{
    public function __construct(
        protected Container $container,
        protected KnowledgeBaseChatProvider $knowledgeBaseProvider
    ) {}

    /**
     * Mengambil atau membuat percakapan aktif untuk pengguna.
     */
    public function getOrCreateActiveConversation(User $user): AiConversation
    {
        $conversation = $user->aiConversations()->latest('updated_at')->first();

        if (! $conversation) {
            $context = RoleKnowledgeBase::getRoleUiContext($user->role, $user->name);
            $conversation = $user->aiConversations()->create([
                'title' => "Percakapan {$context['title']}",
            ]);

            // Tambahkan pesan sambutan awal otomatis sesuai role pengguna
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $context['welcome'],
            ]);
        }

        return $conversation;
    }

    /**
     * Mengirimkan pesan dari pengguna dan memproses jawaban dari provider AI.
     *
     * @return array{
     *     conversation_id: string,
     *     user_message: array{id: string, role: string, content: string, created_at: string},
     *     assistant_reply: array{id: string, role: string, content: string, created_at: string}
     * }
     */
    public function sendMessage(User $user, string $userMessageText, ?string $conversationId = null): array
    {
        $conversation = null;
        if ($conversationId) {
            $conversation = $user->aiConversations()->where('id', $conversationId)->first();
        }

        if (! $conversation) {
            $conversation = $this->getOrCreateActiveConversation($user);
        }

        // 1. Simpan pesan pengguna ke database
        /** @var AiMessage $userMsg */
        $userMsg = $conversation->messages()->create([
            'role' => 'user',
            'content' => trim($userMessageText),
        ]);

        // Perbarui judul percakapan jika masih judul default
        if ($conversation->title === 'Percakapan Asisten Pelayanan Desa' || empty($conversation->title)) {
            $conversation->update([
                'title' => Str::limit(trim($userMessageText), 50),
            ]);
        }

        // 2. Ambil riwayat percakapan sebelumnya untuk konteks
        $history = $conversation->messages()
            ->where('id', '!=', $userMsg->id)
            ->latest('created_at')
            ->take(8)
            ->get()
            ->reverse()
            ->map(fn (AiMessage $m) => [
                'role' => $m->role,
                'content' => $m->content,
            ])
            ->values()
            ->all();

        // 3. Panggil provider AI aktif
        $provider = $this->getProvider();
        $replyText = $provider->reply($userMessageText, $history, $user);

        // 4. Simpan balasan asisten ke database
        /** @var AiMessage $assistantMsg */
        $assistantMsg = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $replyText,
        ]);

        $conversation->touch();

        return [
            'conversation_id' => $conversation->id,
            'user_message' => [
                'id' => $userMsg->id,
                'role' => 'user',
                'content' => $userMsg->content,
                'created_at' => $userMsg->created_at->format('H:i'),
            ],
            'assistant_reply' => [
                'id' => $assistantMsg->id,
                'role' => 'assistant',
                'content' => $assistantMsg->content,
                'created_at' => $assistantMsg->created_at->format('H:i'),
            ],
        ];
    }

    /**
     * Menghapus seluruh riwayat percakapan pengguna untuk memulai sesi baru.
     */
    public function resetConversation(User $user, ?string $conversationId = null): AiConversation
    {
        if ($conversationId) {
            $user->aiConversations()->where('id', $conversationId)->delete();
        } else {
            $user->aiConversations()->delete();
        }

        return $this->getOrCreateActiveConversation($user);
    }

    /**
     * Mendapatkan instansi provider AI yang dikonfigurasi.
     */
    public function getProvider(): AiChatProviderInterface
    {
        $driver = config('ai.chat_driver', 'knowledge_base');

        return match ($driver) {
            'gemini' => $this->container->make(GeminiChatProvider::class),
            'knowledge_base', 'mock' => $this->knowledgeBaseProvider,
            default => $this->knowledgeBaseProvider,
        };
    }
}
