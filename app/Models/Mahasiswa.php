<?php

namespace App\Models;
use App\Models\DiskusiPengaduan;
use App\Models\Notifikasi;
use App\Models\Pengaduan;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Mahasiswa extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'mahasiswas';
    protected $primaryKey = 'id_mahasiswa';

    protected $fillable = [
        'nama',
        'nim',
        'username',
        'password',
        'angkatan',
        'prodi',
        'kelas',
        'email',
        'no_telepon',
        'alamat',
        'foto',
        'email_verified_at',
        'fcm_token',
    ];

    protected $hidden = [
        'password',
    ];

    // Relasi: Satu Mahasiswa punya banyak Pengaduan
    public function pengaduans(): HasMany
    {
        return $this->hasMany(Pengaduan::class, 'id_mahasiswa', 'id_mahasiswa');
    }

    // Relasi: Mahasiswa bisa mengirim banyak pesan Diskusi (Polymorphic)
    public function diskusiSent(): MorphMany
    {
        return $this->morphMany(DiskusiPengaduan::class, 'sender');
    }

    // Relasi: Mahasiswa bisa menerima banyak Notifikasi (Polymorphic)
    public function notifikasis(): MorphMany
    {
        return $this->morphMany(Notifikasi::class, 'notifiable');
    }

    public function getFotoAttribute($value)
    {
        if (!$value || Str::startsWith($value, 'http')) {
            return $value;
        }

        if ($value == 'default_mahasiswa.png') {
            return null;
        }

        return Storage::disk('public')->url($value);
    }


    /**
     * Dapatkan atribut yang harus di-cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
