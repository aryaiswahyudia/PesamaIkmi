<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Tanggapan;
use App\Models\DiskusiPengaduan;
use App\Models\Notifikasi;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;


class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    protected $table = 'users';
    protected $primaryKey = 'id_user';
    protected $fillable = [
        'nama',
        'username',
        'email',
        'password',
        'no_telepon',
        'jabatan',
        'fcm_token',
        'foto_profile', // <-- Ditambahkan agar bisa di-update
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

    // Relasi: Satu User (Operator/Admin) bisa membuat banyak Tanggapan Resmi
    public function tanggapans(): HasMany
    {
        return $this->hasMany(Tanggapan::class, 'id_user', 'id_user');
    }

    // Relasi: User bisa mengirim banyak pesan Diskusi (Polymorphic)
    public function diskusiSent(): MorphMany
    {
        return $this->morphMany(DiskusiPengaduan::class, 'sender');
    }

    public function notifikasis(): MorphMany
    {
        return $this->morphMany(Notifikasi::class, 'notifiable');
    }
    
    public function getFotoProfileAttribute($value)
    {
        if (!$value || Str::startsWith($value, 'http')) {
            return $value;
        }

        if ($value == 'default_user.png') {
            return null;
        }

        return Storage::disk('public')->url($value);
    }

    /**
     * Get the attributes that should be cast.
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
