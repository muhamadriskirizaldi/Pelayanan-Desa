<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterMasyarakatRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Menampilkan halaman landing dan login.
     */
    public function showLanding(): View|RedirectResponse
    {
        // Jika sudah login, langsung arahkan ke dashboard peran masing-masing
        if (Auth::check()) {
            return $this->redirectByRole(Auth::user());
        }

        $demoUsers = app()->isProduction() ? [] : [
            [
                'role' => UserRole::ADMIN,
                'email' => 'admin@desa.id',
                'username' => 'admin',
                'password' => 'Password123!',
                'name' => 'Administrator Sistem',
                'badge' => 'Admin Utama',
            ],
            [
                'role' => UserRole::STAFF,
                'email' => 'staff@desa.id',
                'username' => 'staff_budi',
                'password' => 'Password123!',
                'name' => 'Budi Santoso',
                'badge' => 'Pemeriksaan Admin',
            ],
            [
                'role' => UserRole::SUPERVISOR,
                'email' => 'supervisor@desa.id',
                'username' => 'spv_siti',
                'password' => 'Password123!',
                'name' => 'Siti Rahmawati',
                'badge' => 'Verifikasi Lapangan',
            ],
            [
                'role' => UserRole::KEPALA_DESA,
                'email' => 'kades@desa.id',
                'username' => 'kades_ahmad',
                'password' => 'Password123!',
                'name' => 'H. Ahmad Dahlan',
                'badge' => 'Persetujuan / Kades',
            ],
            [
                'role' => UserRole::MASYARAKAT,
                'email' => 'warga@desa.id',
                'username' => 'jokosusilo',
                'password' => 'Password123!',
                'name' => 'Joko Susilo',
                'badge' => 'Pemohon / Warga',
            ],
        ];

        return view('landing', compact('demoUsers'));
    }

    /**
     * Proses autentikasi login (mendukung email atau username + rate limiting).
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $loginInput = trim((string) $request->input('login'));
        $password = (string) $request->input('password');
        $remember = (bool) $request->input('remember', false);

        // Kunci pembatasan percobaan login (Rate Limiting Brute Force Protection)
        $throttleKey = Str::transliterate(Str::lower($loginInput).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()
                ->withInput($request->only('login', 'remember'))
                ->withErrors([
                    'login' => "Terlalu banyak percobaan masuk yang gagal. Silakan coba kembali dalam {$seconds} detik.",
                ]);
        }

        // Cari user untuk autentikasi kredensial (mendukung Email, Username, Nama Lengkap, No. Telepon, atau NIK)
        $user = User::where(function ($query) use ($loginInput) {
            $query->where('email', $loginInput)
                  ->orWhere('username', $loginInput)
                  ->orWhere('name', $loginInput)
                  ->orWhere('phone_number', $loginInput)
                  ->orWhereHas('residentProfile', function ($rq) use ($loginInput) {
                      $rq->where('nik', $loginInput);
                  });
        })->first();

        // Validasi kredensial (Cegah user enumeration: jangan bocorkan keberadaan akun jika password salah)
        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            return back()
                ->withInput($request->only('login', 'remember'))
                ->withErrors([
                    'login' => 'Email/Username atau kata sandi tidak valid. Silakan periksa kembali kredensial Anda.',
                ]);
        }

        // Pengecekan status akun aktif (hanya setelah password terbukti valid)
        if (! $user->is_active) {
            RateLimiter::hit($throttleKey, 60);

            return back()
                ->withInput($request->only('login', 'remember'))
                ->withErrors([
                    'login' => 'Akun Anda telah dinonaktifkan oleh Administrator Desa. Silakan hubungi aparat desa terkait.',
                ]);
        }

        // Kredensial dan status valid, lakukan login sesi
        Auth::login($user, $remember);

        // Berhasil login: bersihkan rate limiter dan perbarui ID sesi (Cegah session fixation)
        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        /** @var User $authenticatedUser */
        $authenticatedUser = Auth::user();

        return $this->redirectByRole($authenticatedUser)
            ->with('status', 'Selamat datang kembali, '.$authenticatedUser->name.'! Anda login sebagai '.$authenticatedUser->role->label().'.');

        // Gagal login: catat kegagalan pada rate limiter
        RateLimiter::hit($throttleKey, 60);

        return back()
            ->withInput($request->only('login', 'remember'))
            ->withErrors([
                'login' => 'Email/Username atau kata sandi tidak valid. Silakan periksa kembali data Anda.',
            ]);
    }

    /**
     * Tampilkan formulir pendaftaran akun masyarakat.
     */
    public function showRegister(): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectByRole(Auth::user());
        }

        return view('auth.register');
    }

    /**
     * Proses pendaftaran akun masyarakat beserta data kependudukan.
     */
    public function register(RegisterMasyarakatRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'username' => $validated['username'] ?? null,
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'phone_number' => $validated['phone_number'] ?? null,
                'role' => UserRole::MASYARAKAT,
                'is_active' => true,
            ]);

            $user->residentProfile()->create([
                'nik' => $validated['nik'],
                'no_kk' => $validated['no_kk'],
                'tempat_lahir' => $validated['tempat_lahir'],
                'tanggal_lahir' => $validated['tanggal_lahir'],
                'jenis_kelamin' => $validated['jenis_kelamin'],
                'alamat' => $validated['alamat'],
                'rt' => $validated['rt'] ?? null,
                'rw' => $validated['rw'] ?? null,
                'desa' => $validated['desa'],
                'kecamatan' => $validated['kecamatan'],
                'kabupaten' => $validated['kabupaten'],
                'provinsi' => $validated['provinsi'],
                'kode_pos' => $validated['kode_pos'] ?? null,
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('warga.dashboard')
            ->with('status', 'Pendaftaran berhasil! Selamat datang, '.$user->name.'.');
    }

    /**
     * Pengarah rute sentral `/dashboard` ke dashboard spesifik masing-masing role.
     */
    public function dashboard(): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        return $this->redirectByRole($user);
    }

    /**
     * Logout pengguna dan hancurkan sesi.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing')
            ->with('status', 'Anda telah berhasil keluar dari sesi sistem.');
    }

    /**
     * Memperbarui profil akun pengguna saat ini melalui popup modal.
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->phone_number = $validated['phone_number'] ?? null;

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return back()->with('status', 'Profil akun Anda berhasil diperbarui.');
    }

    /**
     * Helper redirect rute berdasarkan role pengguna.
     */
    protected function redirectByRole(User $user): RedirectResponse
    {
        return match ($user->role) {
            UserRole::ADMIN => redirect()->route('admin.dashboard'),
            UserRole::STAFF => redirect()->route('staff.dashboard'),
            UserRole::SUPERVISOR => redirect()->route('supervisor.dashboard'),
            UserRole::KEPALA_DESA => redirect()->route('kades.dashboard'),
            UserRole::MASYARAKAT => redirect()->route('warga.dashboard'),
        };
    }
}
