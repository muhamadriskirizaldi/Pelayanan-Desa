<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    /**
     * Tipe primary key (UUID string non-incrementing).
     *
     * @var string
     */
    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'phone_number',
        'is_active',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Relasi profil kependudukan (Khusus role Masyarakat).
     */
    public function residentProfile(): HasOne
    {
        return $this->hasOne(ResidentProfile::class, 'user_id');
    }

    /**
     * Relasi daftar pengajuan SKTM milik pengguna.
     */
    public function sktmApplications(): HasMany
    {
        return $this->hasMany(SktmApplication::class, 'user_id');
    }

    /**
     * Relasi seluruh notifikasi pengguna.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(AppNotification::class, 'user_id')->latest();
    }

    /**
     * Relasi notifikasi yang belum dibaca pengguna.
     */
    public function unreadNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class, 'user_id')->where('is_read', false)->latest();
    }

    /**
     * Relasi percakapan dengan AI Assistant Pelayanan Desa.
     */
    public function aiConversations(): HasMany
    {
        return $this->hasMany(AiConversation::class, 'user_id')->latest('updated_at');
    }

    /**
     * Periksa apakah pengguna memiliki salah satu dari peran yang ditentukan.
     */
    public function hasRole(UserRole|string ...$roles): bool
    {
        $currentRole = $this->role instanceof UserRole ? $this->role->value : (string) $this->role;

        foreach ($roles as $role) {
            $expectedRole = $role instanceof UserRole ? $role->value : $role;
            if ($currentRole === $expectedRole) {
                return true;
            }
        }

        return false;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::ADMIN);
    }

    public function isStaff(): bool
    {
        return $this->hasRole(UserRole::STAFF);
    }

    public function isSupervisor(): bool
    {
        return $this->hasRole(UserRole::SUPERVISOR);
    }

    public function isKepalaDesa(): bool
    {
        return $this->hasRole(UserRole::KEPALA_DESA);
    }

    public function isMasyarakat(): bool
    {
        return $this->hasRole(UserRole::MASYARAKAT);
    }

    /**
     * Scope query untuk hanya pengguna yang berstatus aktif.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
