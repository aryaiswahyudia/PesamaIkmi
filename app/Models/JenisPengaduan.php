<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisPengaduan extends Model
{
    use HasFactory;

    protected $table = 'jenis_pengaduans';
    protected $primaryKey = 'id_jenis_pengaduan';
    protected $fillable = ['jenis_pengaduan'];

    // Relasi: Satu Jenis Pengaduan punya banyak Pihak Terkait
    public function pihakTerkait(): HasMany
    {
        return $this->hasMany(PihakTerkait::class, 'id_jenis_pengaduan', 'id_jenis_pengaduan');
    }

    // Relasi: Satu Jenis Pengaduan punya banyak Pengaduan
    public function pengaduans(): HasMany
    {
        return $this->hasMany(Pengaduan::class, 'id_jenis_pengaduan', 'id_jenis_pengaduan');
    }
}
