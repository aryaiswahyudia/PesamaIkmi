<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PihakTerkait extends Model
{
    use HasFactory;

    protected $table = 'pihak_terkait';
    protected $primaryKey = 'id_pihak_terkait';
    protected $fillable = ['nama_pihak', 'id_jenis_pengaduan'];

    // Relasi: Satu Pihak Terkait milik satu Jenis Pengaduan (jika tidak null)
    public function jenisPengaduan(): BelongsTo
    {
        return $this->belongsTo(JenisPengaduan::class, 'id_jenis_pengaduan', 'id_jenis_pengaduan');
    }

    // Relasi: Satu Pihak Terkait bisa terkait dengan banyak Pengaduan
    public function pengaduans(): HasMany
    {
        return $this->hasMany(Pengaduan::class, 'id_pihak_terkait', 'id_pihak_terkait');
    }
}
