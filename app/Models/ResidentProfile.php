<?php

namespace App\Models;

use App\Enums\Gender;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResidentProfile extends Model
{
    use HasFactory, HasUuids;

    /**
     * Tipe primary key (UUID non-incrementing).
     *
     * @var string
     */
    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'resident_profiles';

    /**
     * Kolom yang dapat diisi secara massal.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'nik',
        'no_kk',
        'tempat_lahir',
        'tanggal_lahir',
        'jenis_kelamin',
        'alamat',
        'rt',
        'rw',
        'desa',
        'kecamatan',
        'kabupaten',
        'provinsi',
        'kode_pos',
        'agama',
        'pekerjaan',
        'status_perkawinan',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date:Y-m-d',
            'jenis_kelamin' => Gender::class,
        ];
    }

    /**
     * Relasi ke akun pengguna pemilik profil.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Aksesor: Sensor data sensitif NIK untuk keamanan tampilan (UU Pelindungan Data Pribadi).
     * Menampilkan 6 digit awal wilayah dan 4 digit akhir nomor urut, sensor di bagian tengah.
     * Contoh: 320101******0001
     */
    protected function maskedNik(): Attribute
    {
        return Attribute::make(
            get: function () {
                $nik = $this->nik;
                if (! $nik || strlen($nik) < 10) {
                    return $nik;
                }

                return substr($nik, 0, 6).str_repeat('*', max(0, strlen($nik) - 10)).substr($nik, -4);
            }
        );
    }

    /**
     * Aksesor: Sensor data sensitif No KK untuk keamanan tampilan.
     * Contoh: 320101******0005
     */
    protected function maskedNoKk(): Attribute
    {
        return Attribute::make(
            get: function () {
                $noKk = $this->no_kk;
                if (! $noKk || strlen($noKk) < 10) {
                    return $noKk;
                }

                return substr($noKk, 0, 6).str_repeat('*', max(0, strlen($noKk) - 10)).substr($noKk, -4);
            }
        );
    }

    /**
     * Aksesor: Alamat lengkap terformat rapi sesuai standar administrasi persuratan.
     */
    protected function formattedAlamat(): Attribute
    {
        return Attribute::make(
            get: function () {
                $parts = [
                    $this->alamat,
                    ($this->rt ? 'RT '.$this->rt : null),
                    ($this->rw ? 'RW '.$this->rw : null),
                    ($this->desa ? 'Desa/Kel. '.$this->desa : null),
                    ($this->kecamatan ? 'Kec. '.$this->kecamatan : null),
                    ($this->kabupaten ? $this->kabupaten : null),
                    ($this->provinsi ? 'Prov. '.$this->provinsi : null),
                    ($this->kode_pos ? $this->kode_pos : null),
                ];

                return implode(', ', array_filter($parts));
            }
        );
    }
}
