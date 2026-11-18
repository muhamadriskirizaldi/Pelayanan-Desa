<?php

namespace App\Models;

use App\Enums\SktmStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SktmStatusHistory extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sktm_status_histories';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sktm_application_id',
        'user_id',
        'user_name',
        'role',
        'from_status',
        'to_status',
        'status_sebelumnya',
        'status_baru',
        'catatan',
    ];

    /**
     * Relasi ke Permohonan SKTM yang bersangkutan.
     */
    public function sktmApplication(): BelongsTo
    {
        return $this->belongsTo(SktmApplication::class, 'sktm_application_id');
    }

    /**
     * Relasi ke akun pengguna yang melakukan aksi / transisi status.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Alias relasi actor untuk fleksibilitas kode.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Aksesor dan Mutator: status_sebelumnya <-> from_status
     */
    protected function statusSebelumnya(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['from_status'] ?? null,
            set: fn ($value) => ['from_status' => $value instanceof SktmStatus ? $value->value : $value]
        );
    }

    /**
     * Aksesor dan Mutator: status_baru <-> to_status
     */
    protected function statusBaru(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['to_status'] ?? null,
            set: fn ($value) => ['to_status' => $value instanceof SktmStatus ? $value->value : $value]
        );
    }

    /**
     * Alias akses bahasa Inggris: previous_status
     */
    protected function previousStatus(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['from_status'] ?? null,
            set: fn ($value) => ['from_status' => $value instanceof SktmStatus ? $value->value : $value]
        );
    }

    /**
     * Alias akses bahasa Inggris: new_status
     */
    protected function newStatus(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['to_status'] ?? null,
            set: fn ($value) => ['to_status' => $value instanceof SktmStatus ? $value->value : $value]
        );
    }

    /**
     * Alias akses bahasa Inggris: notes
     */
    protected function notes(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['catatan'] ?? null,
            set: fn ($value) => ['catatan' => $value]
        );
    }

    /**
     * Label status sebelumnya dalam Bahasa Indonesia.
     */
    public function getStatusSebelumnyaLabelAttribute(): string
    {
        if (! $this->from_status) {
            return '-';
        }

        $enum = SktmStatus::tryFrom($this->from_status);

        return $enum ? $enum->label() : ucfirst(str_replace('_', ' ', $this->from_status));
    }

    /**
     * Label status baru dalam Bahasa Indonesia.
     */
    public function getStatusBaruLabelAttribute(): string
    {
        if (! $this->to_status) {
            return '-';
        }

        $enum = SktmStatus::tryFrom($this->to_status);

        return $enum ? $enum->label() : ucfirst(str_replace('_', ' ', $this->to_status));
    }
}
