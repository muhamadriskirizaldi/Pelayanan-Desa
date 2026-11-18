<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // 1. Cek autentikasi (Unauthorized - 401 / Redirect Login)
        if (! $user) {
            if ($request->expectsJson()) {
                abort(401, 'Silakan login terlebih dahulu untuk mengakses layanan ini.');
            }

            return redirect()->guest(route('login'))
                ->with('error', 'Sesi Anda belum terautentikasi atau telah berakhir. Silakan masuk terlebih dahulu.');
        }

        // 2. Cek status keaktifan akun
        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors([
                    'login' => 'Akun Anda dinonaktifkan oleh Administrator Desa. Silakan hubungi kantor desa.',
                ]);
        }

        // 3. Normalisasi role pengguna saat ini
        $userRoleValue = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;

        // 4. Kumpulkan daftar peran yang diizinkan (mendukung pemisah koma)
        $allowedRoles = [];
        foreach ($roles as $roleParam) {
            foreach (explode(',', $roleParam) as $singleRole) {
                $allowedRoles[] = trim(strtolower($singleRole));
            }
        }

        // 5. Evaluasi hak akses (Forbidden - 403 jika tidak berhak)
        if (! in_array(strtolower($userRoleValue), $allowedRoles, true)) {
            $userLabel = $user->role instanceof UserRole ? $user->role->label() : strtoupper($userRoleValue);
            $requiredLabels = implode(', ', array_map('strtoupper', $allowedRoles));

            abort(403, "Akses Ditolak (403 Forbidden): Peran Anda sebagai '{$userLabel}' tidak memiliki wewenang untuk mengakses halaman ini. Halaman ini dikhususkan untuk: [{$requiredLabels}].");
        }

        return $next($request);
    }
}
