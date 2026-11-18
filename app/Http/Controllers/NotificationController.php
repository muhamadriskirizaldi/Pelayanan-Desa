<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * Tampilkan Pusat Notifikasi Pelayanan.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $filter = $request->query('filter', 'all');

        $query = $user->notifications();

        if ($filter === 'unread') {
            $query->where('is_read', false);
        } elseif ($filter === 'read') {
            $query->where('is_read', true);
        }

        $notifications = $query->paginate(15)->withQueryString();

        $unreadCount = $user->unreadNotifications()->count();
        $totalCount = $user->notifications()->count();

        return view('notifications.index', compact('notifications', 'filter', 'unreadCount', 'totalCount'));
    }

    /**
     * Tandai satu notifikasi sebagai sudah dibaca dan arahkan ke tautan target.
     */
    public function markAsRead(AppNotification $notification): RedirectResponse
    {
        $user = Auth::user();

        // Keamanan: Pastikan hanya pemilik notifikasi yang dapat mengubah status
        if ($notification->user_id !== $user->id) {
            abort(403, 'Akses Ditolak: Anda bukan pemilik notifikasi ini.');
        }

        $notification->markAsRead();

        if ($notification->action_url) {
            return redirect($notification->action_url);
        }

        return back()->with('status', 'Notifikasi telah ditandai sebagai dibaca.');
    }

    /**
     * Tandai semua notifikasi milik pengguna yang sedang login sebagai sudah dibaca.
     */
    public function markAllAsRead(): RedirectResponse
    {
        $user = Auth::user();

        $user->unreadNotifications()->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return back()->with('status', 'Semua notifikasi berhasil ditandai sebagai dibaca.');
    }

    /**
     * Endpoint API sederhana untuk polling jumlah notifikasi belum dibaca.
     */
    public function unreadCount(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'unread_count' => $user ? $user->unreadNotifications()->count() : 0,
        ]);
    }
}
