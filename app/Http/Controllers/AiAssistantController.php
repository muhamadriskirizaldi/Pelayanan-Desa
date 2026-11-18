<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AiChat\AiChatService;
use App\Services\AiChat\RoleKnowledgeBase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AiAssistantController extends Controller
{
    /**
     * Tampilkan Halaman Antarmuka Chat AI Assistant Terintegrasi Role.
     */
    public function index(Request $request, AiChatService $chatService): View
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user !== null, 401, 'Silakan login terlebih dahulu untuk mengakses AI Assistant.');

        $conversation = $chatService->getOrCreateActiveConversation($user);
        $messages = $conversation->messages;
        $uiContext = RoleKnowledgeBase::getRoleUiContext($user->role, $user->name);

        return view('ai-assistant.index', compact('conversation', 'messages', 'uiContext'));
    }

    /**
     * Kirim Pesan / Pertanyaan ke AI Assistant (dengan Rate Limiting & Konteks Role).
     */
    public function sendMessage(Request $request, AiChatService $chatService): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user !== null, 401, 'Silakan login terlebih dahulu.');

        $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:1000'],
            'conversation_id' => ['nullable', 'string'],
        ], [
            'message.required' => 'Pertanyaan tidak boleh kosong.',
            'message.min' => 'Pertanyaan minimal 2 karakter.',
            'message.max' => 'Pertanyaan maksimal 1000 karakter.',
        ]);

        $result = $chatService->sendMessage(
            user: $user,
            userMessageText: $request->input('message'),
            conversationId: $request->input('conversation_id')
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'role' => $user->role->value,
                'reply' => $result['assistant_reply']['content'] ?? '',
                'timestamp' => now()->format('H:i'),
                'history_count' => count($result),
                'data' => $result,
            ]);
        }

        return redirect()->back();
    }

    /**
     * Bersihkan Riwayat Percakapan (Mulai Sesi Baru).
     */
    public function resetChat(Request $request, AiChatService $chatService): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user !== null, 401, 'Silakan login terlebih dahulu.');

        $conversation = $chatService->resetConversation($user, $request->input('conversation_id'));

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Percakapan baru telah dimulai.',
                'conversation_id' => $conversation->id,
            ]);
        }

        return redirect()->back()->with('status', 'Percakapan baru telah dimulai.');
    }
}
