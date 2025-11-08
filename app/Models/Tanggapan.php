<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Tanggapan extends Model
{
    use HasFactory;

    protected $table = 'tanggapans';
    protected $primaryKey = 'id_tanggapan';
    protected $fillable = [
        'id_pengaduan',
        'id_user',
        'isi_tanggapan',
        'status_tanggapan',
    ];

    // Relasi: Satu Tanggapan milik satu Pengaduan
    public function pengaduan(): BelongsTo
    {
        return $this->belongsTo(Pengaduan::class, 'id_pengaduan', 'id_pengaduan');
    }

    // Relasi: Satu Tanggapan dibuat oleh satu User (Operator/Admin)
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    // Relasi: Satu Tanggapan bisa punya banyak Lampiran (Polymorphic)
    public function lampirans(): MorphMany
    {
        return $this->morphMany(Lampiran::class, 'lampiranable');
    }
}
