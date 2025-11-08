<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class DiskusiPengaduan extends Model
{
    use HasFactory;

    protected $table = 'diskusi_pengaduans';
    protected $primaryKey = 'id_diskusi';
    protected $fillable = [
        'id_pengaduan',
        'sender_id',
        'sender_type',
        'isi_pesan',
    ];

    // Relasi: Satu Pesan Diskusi milik satu Pengaduan
    public function pengaduan(): BelongsTo
    {
        return $this->belongsTo(Pengaduan::class, 'id_pengaduan', 'id_pengaduan');
    }

    // Relasi: Pengirim pesan bisa Mahasiswa atau User (Polymorphic)
    public function sender(): MorphTo
    {
        return $this->morphTo();
    }

    // Relasi: Satu Pesan Diskusi bisa punya banyak Lampiran (Polymorphic)
    public function lampirans(): MorphMany
    {
        return $this->morphMany(Lampiran::class, 'lampiranable');
    }
}
