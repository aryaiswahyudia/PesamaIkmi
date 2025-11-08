<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notifikasi extends Model
{
    use HasFactory;

    protected $table = 'notifikasis';
    protected $primaryKey = 'id_notifikasi';
    protected $fillable = [
        'notifiable_id',
        'notifiable_type',
        'id_pengaduan',
        'tipe_notifikasi',
        'judul_notifikasi',
        'pesan_notifikasi',
        'dibaca',
    ];

    protected $casts = [
        'dibaca' => 'boolean', // Cast 'dibaca' ke boolean
    ];

    // Relasi: Notifikasi ini untuk Mahasiswa atau User (Polymorphic)
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    // Relasi: Notifikasi ini terkait dengan satu Pengaduan (jika tidak null)
    public function pengaduan(): BelongsTo
    {
        return $this->belongsTo(Pengaduan::class, 'id_pengaduan', 'id_pengaduan')
            ->withDefault(); // Menghindari error jika pengaduan tidak ada
    }
}
